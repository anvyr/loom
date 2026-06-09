<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Http;

use Anvyr\Loom\Contracts\MiddlewareInterface;
use Anvyr\Loom\Http\Middleware\Pipeline;
use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;
use Anvyr\Loom\Tests\Support\TestCase;

final class PipelineTest extends TestCase
{
    public function test_pipeline_executes_single_middleware(): void
    {
        $pipeline = new Pipeline();
        $executed = false;

        $result = $pipeline
            ->send($this->makeRequest('GET', '/'))
            ->through([
                function ($request, $next) use (&$executed) {
                    $executed = true;
                    return $next($request);
                },
            ])
            ->then(fn ($request) => Response::html('done'));

        $this->assertTrue($executed);
        $this->assertSame('done', $result->getContent());
    }

    public function test_pipeline_executes_middleware_in_order(): void
    {
        $order = [];
        $pipeline = new Pipeline();

        $pipeline
            ->send($this->makeRequest('GET', '/'))
            ->through([
                function ($request, $next) use (&$order) {
                    $order[] = 'first-before';
                    $response = $next($request);
                    $order[] = 'first-after';
                    return $response;
                },
                function ($request, $next) use (&$order) {
                    $order[] = 'second-before';
                    $response = $next($request);
                    $order[] = 'second-after';
                    return $response;
                },
            ])
            ->then(function ($request) use (&$order) {
                $order[] = 'handler';
                return Response::html('ok');
            });

        $this->assertSame([
            'first-before',
            'second-before',
            'handler',
            'second-after',
            'first-after',
        ], $order);
    }

    public function test_pipeline_can_short_circuit(): void
    {
        $reachedHandler = false;
        $pipeline = new Pipeline();

        $result = $pipeline
            ->send($this->makeRequest('GET', '/'))
            ->through([
                function ($request, $next) {
                    return Response::html('blocked', 403);
                },
                function ($request, $next) {
                    // This should never execute
                    return $next($request);
                },
            ])
            ->then(function ($request) use (&$reachedHandler) {
                $reachedHandler = true;
                return Response::html('ok');
            });

        $this->assertFalse($reachedHandler);
        $this->assertSame(403, $result->getStatus());
        $this->assertSame('blocked', $result->getContent());
    }

    public function test_pipeline_with_no_middleware(): void
    {
        $pipeline = new Pipeline();

        $result = $pipeline
            ->send($this->makeRequest('GET', '/'))
            ->through([])
            ->then(fn ($request) => Response::html('direct'));

        $this->assertSame('direct', $result->getContent());
    }

    public function test_pipeline_middleware_can_modify_response(): void
    {
        $pipeline = new Pipeline();

        $result = $pipeline
            ->send($this->makeRequest('GET', '/'))
            ->through([
                function ($request, $next) {
                    $response = $next($request);
                    return $response->header('X-Modified', 'yes');
                },
            ])
            ->then(fn ($request) => Response::html('content'));

        $this->assertSame('yes', $result->getHeader('X-Modified'));
    }

    public function test_pipeline_passes_request_modifications(): void
    {
        $pipeline = new Pipeline();
        $receivedPath = null;

        $result = $pipeline
            ->send($this->makeRequest('GET', '/original'))
            ->through([
                function ($request, $next) {
                    // Middleware could modify request attributes if needed
                    return $next($request);
                },
            ])
            ->then(function ($request) use (&$receivedPath) {
                $receivedPath = $request->path();
                return Response::html('done');
            });

        $this->assertSame('/original', $receivedPath);
    }

    public function test_pipeline_with_class_middleware(): void
    {
        $pipeline = new Pipeline();

        $result = $pipeline
            ->send($this->makeRequest('GET', '/'))
            ->through([TestMiddleware::class])
            ->then(fn ($request) => Response::html('handler'));

        $this->assertArrayHasKey('X-Test-Header', $result->getHeaders());
        $this->assertSame('middleware-added', $result->getHeader('X-Test-Header'));
    }
}

class TestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        return $response->header('X-Test-Header', 'middleware-added');
    }
}
