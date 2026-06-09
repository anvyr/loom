<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Http;

use Anvyr\Loom\Exceptions\ValidationException;
use Anvyr\Loom\Tests\Support\TestCase;

final class RequestTest extends TestCase
{
    public function test_method_returns_uppercased_request_method(): void
    {
        $request = $this->makeRequest('post', '/test');
        $this->assertSame('POST', $request->method());
    }

    public function test_path_strips_query_string(): void
    {
        $request = $this->makeRequest('GET', '/page?foo=bar&baz=qux');
        $this->assertSame('/page', $request->path());
    }

    public function test_path_trims_trailing_slash(): void
    {
        $request = $this->makeRequest('GET', '/blog/');
        $this->assertSame('/blog', $request->path());
    }

    public function test_path_handles_empty_as_root(): void
    {
        $_SERVER['REQUEST_URI'] = '';
        $request = \Anvyr\Loom\Http\Request::capture();
        $this->assertSame('/', $request->path());
    }

    public function test_path_prefix_strips_tenant_prefix(): void
    {
        $request = $this->makeRequest('GET', '/tenant-a/dashboard');
        $request->setPathPrefix('/tenant-a');

        $this->assertSame('/dashboard', $request->path());
    }

    public function test_raw_path_ignores_prefix(): void
    {
        $request = $this->makeRequest('GET', '/tenant-a/dashboard');
        $request->setPathPrefix('/tenant-a');

        $this->assertSame('/tenant-a/dashboard', $request->rawPath());
    }

    public function test_url_builds_full_url(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/page?query=1',
            'HTTP_HOST' => 'example.com',
        ];
        $request = \Anvyr\Loom\Http\Request::capture();

        $this->assertSame('http://example.com/page', $request->url());
    }

    public function test_url_uses_https_when_secure(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/secure',
            'HTTP_HOST' => 'example.com',
            'HTTPS' => 'on',
        ];
        $request = \Anvyr\Loom\Http\Request::capture();

        $this->assertStringStartsWith('https://', $request->url());
    }

    public function test_host_extracts_hostname_without_port(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'Example.COM:8080',
        ];
        $request = \Anvyr\Loom\Http\Request::capture();

        $this->assertSame('example.com', $request->host());
    }

    public function test_is_secure_detects_https(): void
    {
        $request = $this->makeRequest('GET', '/');
        $this->assertFalse($request->isSecure());

        $_SERVER['HTTPS'] = 'on';
        $request = \Anvyr\Loom\Http\Request::capture();
        $this->assertTrue($request->isSecure());
    }

    public function test_trusted_proxy_uses_forwarded_scheme_and_host(): void
    {
        config([
            'http.trusted_proxies.enabled' => true,
            'http.trusted_proxies.proxies' => ['127.0.0.1'],
        ]);

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'internal.local',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'app.example.com',
        ];

        $request = \Anvyr\Loom\Http\Request::capture();

        $this->assertTrue($request->isSecure());
        $this->assertSame('app.example.com', $request->host());
        $this->assertSame('https://app.example.com/dashboard', $request->url());
    }

    public function test_untrusted_proxy_ignores_forwarded_headers(): void
    {
        config([
            'http.trusted_proxies.enabled' => true,
            'http.trusted_proxies.proxies' => ['10.0.0.1'],
        ]);

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/dashboard',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'internal.local',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'app.example.com',
        ];

        $request = \Anvyr\Loom\Http\Request::capture();

        $this->assertFalse($request->isSecure());
        $this->assertSame('internal.local', $request->host());
        $this->assertSame('http://internal.local/dashboard', $request->url());
    }

    public function test_input_merges_get_and_post(): void
    {
        $_GET = ['from_get' => 'get_value'];
        $_POST = ['from_post' => 'post_value'];
        $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'];

        $request = \Anvyr\Loom\Http\Request::capture();

        $this->assertSame('get_value', $request->input('from_get'));
        $this->assertSame('post_value', $request->input('from_post'));
        $this->assertSame('default', $request->input('missing', 'default'));
    }

    public function test_all_returns_merged_data(): void
    {
        $_GET = ['a' => '1'];
        $_POST = ['b' => '2'];
        $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'];

        $request = \Anvyr\Loom\Http\Request::capture();

        $this->assertSame(['a' => '1', 'b' => '2'], $request->all());
    }

    public function test_only_filters_keys(): void
    {
        $request = $this->makeRequest('POST', '/', ['a' => '1', 'b' => '2', 'c' => '3']);

        $this->assertSame(['a' => '1', 'c' => '3'], $request->only(['a', 'c']));
    }

    public function test_except_excludes_keys(): void
    {
        $request = $this->makeRequest('POST', '/', ['a' => '1', 'b' => '2', 'c' => '3']);

        $this->assertSame(['a' => '1', 'c' => '3'], $request->except(['b']));
    }

    public function test_has_checks_existence(): void
    {
        $request = $this->makeRequest('POST', '/', ['exists' => 'yes']);

        $this->assertTrue($request->has('exists'));
        $this->assertFalse($request->has('nope'));
    }

    public function test_query_returns_only_get_params(): void
    {
        $_GET = ['q' => 'search'];
        $_POST = ['q' => 'post-search'];
        $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'];

        $request = \Anvyr\Loom\Http\Request::capture();

        $this->assertSame('search', $request->query('q'));
    }

    public function test_post_returns_only_post_params(): void
    {
        $_GET = ['field' => 'get'];
        $_POST = ['field' => 'post'];
        $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'];

        $request = \Anvyr\Loom\Http\Request::capture();

        $this->assertSame('post', $request->post('field'));
    }

    public function test_file_returns_uploaded_file(): void
    {
        $_FILES = [
            'avatar' => [
                'name' => 'photo.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/phpABC123',
                'error' => UPLOAD_ERR_OK,
                'size' => 12345,
            ],
        ];
        $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'];

        $request = \Anvyr\Loom\Http\Request::capture();

        $file = $request->file('avatar');
        $this->assertSame('photo.jpg', $file['name']);
        $this->assertNull($request->file('nonexistent'));
    }

    public function test_cookie_returns_cookie_value(): void
    {
        $_COOKIE = ['session' => 'abc123'];
        $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'];

        $request = \Anvyr\Loom\Http\Request::capture();

        $this->assertSame('abc123', $request->cookie('session'));
        $this->assertSame('default', $request->cookie('missing', 'default'));
    }

    public function test_header_returns_http_header(): void
    {
        $request = $this->makeRequest('GET', '/', [], ['Authorization' => 'Bearer token123']);

        $this->assertSame('Bearer token123', $request->header('Authorization'));
    }

    public function test_user_agent_returns_ua_string(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_USER_AGENT' => 'TestBot/1.0',
        ];
        $request = \Anvyr\Loom\Http\Request::capture();

        $this->assertSame('TestBot/1.0', $request->userAgent());
    }

    public function test_ip_returns_remote_addr(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'REMOTE_ADDR' => '192.168.1.1',
        ];
        $request = \Anvyr\Loom\Http\Request::capture();

        $this->assertSame('192.168.1.1', $request->ip());
    }

    public function test_ip_uses_forwarded_for_when_proxy_is_trusted(): void
    {
        config([
            'http.trusted_proxies.enabled' => true,
            'http.trusted_proxies.proxies' => ['127.0.0.1'],
        ]);

        $_SERVER = [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.5, 10.0.0.1',
        ];

        $request = \Anvyr\Loom\Http\Request::capture();

        $this->assertSame('203.0.113.5', $request->ip());
    }

    public function test_ajax_detects_xhr(): void
    {
        $request = $this->makeRequest('GET', '/', [], ['X-Requested-With' => 'XMLHttpRequest']);
        $this->assertTrue($request->ajax());

        $request = $this->makeRequest('GET', '/');
        $this->assertFalse($request->ajax());
    }

    public function test_expects_json_detects_accept_header(): void
    {
        $request = $this->makeRequest('GET', '/', [], ['Accept' => 'application/json']);
        $this->assertTrue($request->expectsJson());

        $request = $this->makeRequest('GET', '/', [], ['Accept' => 'text/html']);
        $this->assertFalse($request->expectsJson());
    }

    public function test_validate_returns_validated_data(): void
    {
        $request = $this->makeRequest('POST', '/', ['email' => 'test@example.com', 'name' => 'John']);

        $validated = $request->validate([
            'email' => 'required|email',
            'name' => 'required|min:2',
        ]);

        $this->assertSame('test@example.com', $validated['email']);
        $this->assertSame('John', $validated['name']);
    }

    public function test_validate_throws_on_failure(): void
    {
        $request = $this->makeRequest('POST', '/', ['email' => 'not-an-email']);

        $this->expectException(ValidationException::class);
        $request->validate(['email' => 'required|email']);
    }

    public function test_is_json_detects_json_content_type(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/',
            'CONTENT_TYPE' => 'application/json',
        ];
        $request = \Anvyr\Loom\Http\Request::capture();

        $this->assertTrue($request->isJson());
    }
}
