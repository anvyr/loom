<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Http;

use Anvyr\Loom\Http\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function test_html_creates_response_with_correct_headers(): void
    {
        $response = Response::html('<h1>Hello</h1>', 200);

        $this->assertSame('<h1>Hello</h1>', $response->getContent());
        $this->assertSame(200, $response->getStatus());
        $this->assertSame('text/html; charset=utf-8', $response->getHeader('Content-Type'));
    }

    public function test_html_accepts_custom_status(): void
    {
        $response = Response::html('Error page', 500);

        $this->assertSame(500, $response->getStatus());
    }

    public function test_json_encodes_array(): void
    {
        $response = Response::json(['key' => 'value']);

        $this->assertSame('{"key":"value"}', $response->getContent());
        $this->assertSame('application/json; charset=utf-8', $response->getHeader('Content-Type'));
    }

    public function test_json_encodes_object(): void
    {
        $obj = new \stdClass();
        $obj->name = 'Test';

        $response = Response::json($obj);

        $this->assertSame('{"name":"Test"}', $response->getContent());
    }

    public function test_json_with_custom_status(): void
    {
        $response = Response::json(['created' => true], 201);

        $this->assertSame(201, $response->getStatus());
    }

    public function test_redirect_sets_location_header(): void
    {
        $response = Response::redirect('/dashboard');

        $this->assertSame(302, $response->getStatus());
        $this->assertSame('/dashboard', $response->getHeader('Location'));
        $this->assertSame('', $response->getContent());
    }

    public function test_redirect_allows_custom_status(): void
    {
        $response = Response::redirect('/new-location', 301);

        $this->assertSame(301, $response->getStatus());
    }

    public function test_not_found_returns_404(): void
    {
        $response = Response::notFound();

        $this->assertSame(404, $response->getStatus());
        $this->assertSame('Not Found', $response->getContent());
    }

    public function test_not_found_accepts_custom_message(): void
    {
        $response = Response::notFound('Page does not exist');

        $this->assertSame('Page does not exist', $response->getContent());
    }

    public function test_error_returns_custom_status_and_message(): void
    {
        $response = Response::error('Something went wrong', 503);

        $this->assertSame(503, $response->getStatus());
        $this->assertSame('Something went wrong', $response->getContent());
    }

    public function test_method_not_allowed_sets_allow_header(): void
    {
        $response = Response::methodNotAllowed(['GET', 'POST']);

        $this->assertSame(405, $response->getStatus());
        $this->assertSame('GET, POST', $response->getHeader('Allow'));
    }

    public function test_method_not_allowed_deduplicates_methods(): void
    {
        $response = Response::methodNotAllowed(['get', 'GET', 'post']);

        $allow = $response->getHeader('Allow');
        $methods = array_map('trim', explode(',', $allow));
        sort($methods);

        $this->assertSame(['GET', 'POST'], $methods);
    }

    public function test_file_sets_content_type_and_length(): void
    {
        $path = sys_get_temp_dir() . '/response-test-' . uniqid() . '.txt';
        file_put_contents($path, 'Hello World');

        try {
            $response = Response::file($path, 'text/plain');

            $this->assertSame('text/plain', $response->getHeader('Content-Type'));
            $this->assertSame('11', $response->getHeader('Content-Length'));
            $this->assertSame(200, $response->getStatus());
        } finally {
            @unlink($path);
        }
    }

    public function test_file_auto_detects_mime_type(): void
    {
        $path = sys_get_temp_dir() . '/response-test-' . uniqid() . '.html';
        file_put_contents($path, '<html></html>');

        try {
            $response = Response::file($path);

            $this->assertNotNull($response->getHeader('Content-Type'));
        } finally {
            @unlink($path);
        }
    }

    public function test_file_throws_for_nonexistent_file(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('File not readable');

        Response::file('/nonexistent/path.txt');
    }

    public function test_file_accepts_custom_headers(): void
    {
        $path = sys_get_temp_dir() . '/response-test-' . uniqid() . '.txt';
        file_put_contents($path, 'content');

        try {
            $response = Response::file($path, 'text/plain', [
                'Content-Disposition' => 'attachment; filename="download.txt"',
            ]);

            $this->assertSame('attachment; filename="download.txt"', $response->getHeader('Content-Disposition'));
        } finally {
            @unlink($path);
        }
    }

    public function test_header_method_sets_single_header(): void
    {
        $response = new Response('content');
        $response->header('X-Custom', 'value');

        $this->assertSame('value', $response->getHeader('X-Custom'));
    }

    public function test_headers_method_sets_multiple_headers(): void
    {
        $response = new Response('content');
        $response->headers([
            'X-First' => 'one',
            'X-Second' => 'two',
        ]);

        $this->assertSame('one', $response->getHeader('X-First'));
        $this->assertSame('two', $response->getHeader('X-Second'));
    }

    public function test_status_method_changes_status(): void
    {
        $response = new Response('content');
        $response->status(201);

        $this->assertSame(201, $response->getStatus());
    }

    public function test_fluent_interface(): void
    {
        $response = (new Response('test'))
            ->header('X-One', '1')
            ->headers(['X-Two' => '2'])
            ->status(201);

        $this->assertSame(201, $response->getStatus());
        $this->assertSame('1', $response->getHeader('X-One'));
        $this->assertSame('2', $response->getHeader('X-Two'));
    }

    public function test_get_headers_returns_all_headers(): void
    {
        $response = Response::html('content')
            ->header('X-Custom', 'value');

        $headers = $response->getHeaders();

        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertArrayHasKey('X-Custom', $headers);
    }

    public function test_get_header_is_case_insensitive(): void
    {
        $response = (new Response('test'))
            ->header('Content-Type', 'text/plain');

        $this->assertSame('text/plain', $response->getHeader('content-type'));
        $this->assertSame('text/plain', $response->getHeader('CONTENT-TYPE'));
    }

    public function test_get_header_returns_null_for_missing(): void
    {
        $response = new Response('test');

        $this->assertNull($response->getHeader('X-Missing'));
    }
}
