<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Http\Client;

use Anvyr\Loom\Http\Client\HttpRequestException;
use Anvyr\Loom\Http\Client\HttpResponse;
use Anvyr\Loom\Tests\Support\TestCase;

final class HttpResponseTest extends TestCase
{
    public function test_status_returns_status_code(): void
    {
        $response = new HttpResponse(200, '{}', []);
        $this->assertSame(200, $response->status());
    }

    public function test_body_returns_raw_body(): void
    {
        $response = new HttpResponse(200, 'hello world', []);
        $this->assertSame('hello world', $response->body());
    }

    public function test_json_decodes_body(): void
    {
        $response = new HttpResponse(200, '{"name":"Anvyr Loom","version":2}', []);
        $data = $response->json();

        $this->assertSame('Anvyr Loom', $data['name']);
        $this->assertSame(2, $data['version']);
    }

    public function test_json_throws_on_invalid_json(): void
    {
        $response = new HttpResponse(200, 'not json', []);

        $this->expectException(\JsonException::class);
        $response->json();
    }

    public function test_headers_returns_all_headers(): void
    {
        $headers = [
            'Content-Type' => ['application/json'],
            'X-Request-Id' => ['abc-123'],
        ];
        $response = new HttpResponse(200, '', $headers);

        $this->assertSame($headers, $response->headers());
    }

    public function test_header_returns_first_value_case_insensitive(): void
    {
        $headers = [
            'Content-Type' => ['application/json; charset=utf-8'],
            'X-Custom' => ['value1', 'value2'],
        ];
        $response = new HttpResponse(200, '', $headers);

        $this->assertSame('application/json; charset=utf-8', $response->header('content-type'));
        $this->assertSame('value1', $response->header('X-Custom'));
        $this->assertNull($response->header('Missing'));
    }

    public function test_ok_returns_true_for_2xx(): void
    {
        $this->assertTrue((new HttpResponse(200, '', []))->ok());
        $this->assertTrue((new HttpResponse(201, '', []))->ok());
        $this->assertTrue((new HttpResponse(204, '', []))->ok());
        $this->assertFalse((new HttpResponse(301, '', []))->ok());
        $this->assertFalse((new HttpResponse(404, '', []))->ok());
        $this->assertFalse((new HttpResponse(500, '', []))->ok());
    }

    public function test_successful_is_alias_for_ok(): void
    {
        $ok = new HttpResponse(200, '', []);
        $fail = new HttpResponse(500, '', []);

        $this->assertTrue($ok->successful());
        $this->assertFalse($fail->successful());
    }

    public function test_redirect_returns_true_for_3xx(): void
    {
        $this->assertTrue((new HttpResponse(301, '', []))->redirect());
        $this->assertTrue((new HttpResponse(302, '', []))->redirect());
        $this->assertFalse((new HttpResponse(200, '', []))->redirect());
        $this->assertFalse((new HttpResponse(404, '', []))->redirect());
    }

    public function test_client_error_returns_true_for_4xx(): void
    {
        $this->assertTrue((new HttpResponse(400, '', []))->clientError());
        $this->assertTrue((new HttpResponse(404, '', []))->clientError());
        $this->assertTrue((new HttpResponse(422, '', []))->clientError());
        $this->assertFalse((new HttpResponse(200, '', []))->clientError());
        $this->assertFalse((new HttpResponse(500, '', []))->clientError());
    }

    public function test_server_error_returns_true_for_5xx(): void
    {
        $this->assertTrue((new HttpResponse(500, '', []))->serverError());
        $this->assertTrue((new HttpResponse(503, '', []))->serverError());
        $this->assertFalse((new HttpResponse(200, '', []))->serverError());
        $this->assertFalse((new HttpResponse(404, '', []))->serverError());
    }

    public function test_failed_returns_true_for_4xx_and_5xx(): void
    {
        $this->assertTrue((new HttpResponse(400, '', []))->failed());
        $this->assertTrue((new HttpResponse(500, '', []))->failed());
        $this->assertFalse((new HttpResponse(200, '', []))->failed());
        $this->assertFalse((new HttpResponse(302, '', []))->failed());
    }

    public function test_throw_does_nothing_on_success(): void
    {
        $response = new HttpResponse(200, 'ok', []);
        $this->assertSame($response, $response->throw());
    }

    public function test_throw_throws_on_client_error(): void
    {
        $response = new HttpResponse(422, '{"error":"Unprocessable"}', []);

        try {
            $response->throw();
            $this->fail('Expected HttpRequestException');
        } catch (HttpRequestException $e) {
            $this->assertSame(422, $e->getCode());
            $this->assertSame($response, $e->response());
            $this->assertStringContainsString('422', $e->getMessage());
        }
    }

    public function test_throw_throws_on_server_error(): void
    {
        $response = new HttpResponse(500, 'Internal Server Error', []);

        $this->expectException(HttpRequestException::class);
        $response->throw();
    }
}
