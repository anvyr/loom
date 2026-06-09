<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Http;

use Anvyr\Loom\Core\EventDispatcher;
use Anvyr\Loom\Exceptions\Handler;
use Anvyr\Loom\Http\Middleware\ErrorHandlingMiddleware;
use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;
use Anvyr\Loom\Tests\Support\TestCase;
use RuntimeException;

final class ErrorHandlingMiddlewareTest extends TestCase
{
    public function test_passes_through_when_no_exception(): void
    {
        $handler = new RecordingExceptionHandler(new EventDispatcher());
        $middleware = new ErrorHandlingMiddleware($handler);
        $request = $this->makeRequest('GET', '/');

        $response = $middleware->handle($request, fn (Request $req) => Response::html('ok'));

        $this->assertSame('ok', $response->getContent());
        $this->assertSame(200, $response->getStatus());
        $this->assertFalse($handler->reported);
        $this->assertFalse($handler->rendered);
    }

    public function test_reports_and_renders_on_exception(): void
    {
        $handler = new RecordingExceptionHandler(new EventDispatcher());
        $middleware = new ErrorHandlingMiddleware($handler);
        $request = $this->makeRequest('GET', '/');

        $response = $middleware->handle($request, function () {
            throw new RuntimeException('Boom');
        });

        $this->assertTrue($handler->reported);
        $this->assertTrue($handler->rendered);
        $this->assertSame(500, $response->getStatus());
        $this->assertSame('handled', $response->getContent());
    }
}

final class RecordingExceptionHandler extends Handler
{
    public bool $reported = false;
    public bool $rendered = false;

    public function report(\Throwable $e, Request $request): void
    {
        $this->reported = true;
    }

    public function render(\Throwable $e, Request $request): Response
    {
        $this->rendered = true;
        return Response::error('handled', 500);
    }
}
