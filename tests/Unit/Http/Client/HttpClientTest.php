<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Http\Client;

use Anvyr\Loom\Http\Client\HttpClient;
use Anvyr\Loom\Http\Client\HttpClientException;
use Anvyr\Loom\Tests\Support\TestCase;

/**
 * Tests the HttpClient against a real PHP built-in server.
 *
 * A minimal router script echoes back request details as JSON,
 * letting us verify methods, headers, bodies, and query strings
 * without external dependencies.
 */
final class HttpClientTest extends TestCase
{
    private static ?int $serverPid = null;
    private static string $baseUrl;
    private static string $routerScript;
    private static int $port;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$routerScript = sys_get_temp_dir() . '/loom-http-test-router.php';

        file_put_contents(self::$routerScript, <<<'PHP'
<?php
// Minimal echo server — returns request metadata as JSON.
header('Content-Type: application/json');
header('X-Test-Header: present');

$body = file_get_contents('php://input');

echo json_encode([
    'method'  => $_SERVER['REQUEST_METHOD'],
    'path'    => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
    'query'   => $_GET,
    'headers' => getallheaders(),
    'body'    => $body,
    'json'    => json_decode($body, true),
]);
PHP);

        // Find a free port
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($sock, '127.0.0.1', 0);
        socket_getsockname($sock, $addr, $port);
        socket_close($sock);
        self::$port = $port;
        self::$baseUrl = "http://127.0.0.1:{$port}";

        // Start PHP built-in server
        $cmd = sprintf(
            'exec php -S 127.0.0.1:%d -t %s %s > /dev/null 2>&1 &',
            self::$port,
            escapeshellarg(sys_get_temp_dir()),
            escapeshellarg(self::$routerScript),
        );

        exec($cmd);

        // Give the server a moment to start
        $maxWait = 50; // 50 × 20ms = 1s max
        while ($maxWait-- > 0) {
            $fp = @fsockopen('127.0.0.1', self::$port, $errno, $errstr, 0.1);
            if ($fp !== false) {
                fclose($fp);
                break;
            }
            usleep(20_000);
        }

        // Track PID for cleanup via lsof
        $pid = trim((string) shell_exec('lsof -ti tcp:' . self::$port . ' 2>/dev/null'));
        if ($pid !== '' && is_numeric($pid)) {
            self::$serverPid = (int) $pid;
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$serverPid !== null) {
            posix_kill(self::$serverPid, SIGTERM);
        }

        if (file_exists(self::$routerScript)) {
            unlink(self::$routerScript);
        }

        parent::tearDownAfterClass();
    }

    private function client(string $baseUrl = '', array $headers = []): HttpClient
    {
        return new HttpClient($baseUrl ?: self::$baseUrl, $headers, 2, 5);
    }

    // ──── GET ────────────────────────────────────────────────────

    public function test_get_request(): void
    {
        $response = $this->client()->get('/echo');

        $this->assertTrue($response->ok());
        $data = $response->json();
        $this->assertSame('GET', $data['method']);
        $this->assertSame('/echo', $data['path']);
    }

    public function test_get_with_query_params(): void
    {
        $response = $this->client()->get('/search', [
            'query' => ['q' => 'loom', 'page' => '2'],
        ]);

        $data = $response->json();
        $this->assertSame('loom', $data['query']['q']);
        $this->assertSame('2', $data['query']['page']);
    }

    // ──── POST ───────────────────────────────────────────────────

    public function test_post_json(): void
    {
        $response = $this->client()->post('/items', [
            'json' => ['name' => 'Widget', 'price' => 9.99],
        ]);

        $data = $response->json();
        $this->assertSame('POST', $data['method']);
        $this->assertSame('Widget', $data['json']['name']);
        $this->assertSame(9.99, $data['json']['price']);
    }

    public function test_post_form(): void
    {
        $response = $this->client()->post('/login', [
            'form' => ['username' => 'admin', 'password' => 'secret'],
        ]);

        $data = $response->json();
        $this->assertSame('POST', $data['method']);
        $this->assertStringContainsString('username=admin', $data['body']);
    }

    public function test_post_raw_body(): void
    {
        $response = $this->client()->post('/raw', [
            'body' => '<xml>payload</xml>',
            'headers' => ['Content-Type' => 'application/xml'],
        ]);

        $data = $response->json();
        $this->assertSame('<xml>payload</xml>', $data['body']);
    }

    // ──── PUT / PATCH / DELETE ───────────────────────────────────

    public function test_put_request(): void
    {
        $response = $this->client()->put('/items/1', [
            'json' => ['name' => 'Updated'],
        ]);

        $data = $response->json();
        $this->assertSame('PUT', $data['method']);
    }

    public function test_patch_request(): void
    {
        $response = $this->client()->patch('/items/1', [
            'json' => ['price' => 19.99],
        ]);

        $data = $response->json();
        $this->assertSame('PATCH', $data['method']);
    }

    public function test_delete_request(): void
    {
        $response = $this->client()->delete('/items/1');

        $data = $response->json();
        $this->assertSame('DELETE', $data['method']);
    }

    // ──── Headers ────────────────────────────────────────────────

    public function test_default_headers_sent_with_every_request(): void
    {
        $client = $this->client(self::$baseUrl, ['X-Api-Key' => 'abc123']);
        $response = $client->get('/headers');

        $data = $response->json();
        $this->assertSame('abc123', $data['headers']['X-Api-Key'] ?? null);
    }

    public function test_per_request_headers_override(): void
    {
        $client = $this->client(self::$baseUrl, ['X-Default' => 'original']);
        $response = $client->get('/headers', [
            'headers' => ['X-Default' => 'overridden', 'X-Extra' => 'yes'],
        ]);

        $data = $response->json();
        $this->assertSame('overridden', $data['headers']['X-Default'] ?? null);
        $this->assertSame('yes', $data['headers']['X-Extra'] ?? null);
    }

    public function test_bearer_auth(): void
    {
        $response = $this->client()->get('/auth', [
            'bearer' => 'my-token-123',
        ]);

        $data = $response->json();
        $this->assertSame('Bearer my-token-123', $data['headers']['Authorization'] ?? null);
    }

    // ──── Response object ────────────────────────────────────────

    public function test_response_header_returns_server_header(): void
    {
        $response = $this->client()->get('/');

        $this->assertSame('present', $response->header('X-Test-Header'));
        $this->assertSame('application/json', $response->header('Content-Type'));
    }

    // ──── Base URL + relative paths ──────────────────────────────

    public function test_absolute_url_ignores_base_url(): void
    {
        $client = $this->client('http://should-not-be-used.test');
        $response = $client->get(self::$baseUrl . '/absolute');

        $this->assertTrue($response->ok());
        $data = $response->json();
        $this->assertSame('/absolute', $data['path']);
    }

    public function test_relative_url_prepends_base_url(): void
    {
        $response = $this->client()->get('/relative');

        $data = $response->json();
        $this->assertSame('/relative', $data['path']);
    }

    // ──── Error handling ─────────────────────────────────────────

    public function test_connection_failure_throws_client_exception(): void
    {
        $client = new HttpClient('http://127.0.0.1:1', timeout: 1, connectTimeout: 1);

        $this->expectException(HttpClientException::class);
        $client->get('/');
    }

    // ──── Container registration ─────────────────────────────────

    public function test_client_registered_in_container(): void
    {
        $client = app(HttpClient::class);

        $this->assertInstanceOf(HttpClient::class, $client);
    }

    public function test_container_returns_singleton(): void
    {
        $a = app(HttpClient::class);
        $b = app(HttpClient::class);

        $this->assertSame($a, $b);
    }
}
