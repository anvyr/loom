<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Router;

use Anvyr\Loom\Core\EventDispatcher;
use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;
use Anvyr\Loom\Http\Routing\Router;
use Anvyr\Loom\Tests\Support\Doubles\Http\RecordingMiddleware;
use Anvyr\Loom\Tests\Support\TestCase;

final class RouterMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/pipeline';
    }

    public function test_middleware_pipeline_orders_calls_correctly(): void
    {
        $router = new Router(new EventDispatcher());

        $log = [];

        $router->registerMiddleware('probe', function (Request $request, callable $next) use (&$log): Response {
            $log[] = 'alias-before';
            $response = $next($request);
            $log[] = 'alias-after';
            return $response;
        });

        $router->pushMiddleware('probe');

        $router->get('/pipeline', function (Request $request) use (&$log): Response {
            $log[] = 'handler';
            return Response::html('ok');
        })->middleware(function (Request $request, callable $next) use (&$log): Response {
            $log[] = 'route-before';
            $response = $next($request);
            $log[] = 'route-after';
            return $response;
        });

        $response = $router->dispatch(new Request());

        $this->assertSame(['alias-before', 'route-before', 'handler', 'route-after', 'alias-after'], $log);
        $this->assertSame(200, $response->getStatus());
    }

    public function test_middleware_class_alias_executes_via_pipeline(): void
    {
        RecordingMiddleware::$calls = [];

        $router = new Router(new EventDispatcher());
        $router->registerMiddleware('record', RecordingMiddleware::class);
        $router->pushMiddleware('record');

        $router->get('/pipeline', fn (Request $request): Response => Response::html('ok'));

        $router->dispatch(new Request());

        $this->assertSame(['before', 'after'], RecordingMiddleware::$calls);
    }

    public function test_single_argument_middleware_can_short_circuit(): void
    {
        $router = new Router(new EventDispatcher());

        $handlerExecuted = false;

        $router->registerMiddleware('block', function (Request $request): Response {
            return Response::error('blocked', 403);
        });

        $router->pushMiddleware('block');

        $router->get('/pipeline', function () use (&$handlerExecuted): Response {
            $handlerExecuted = true;
            return Response::html('ok');
        });

        $response = $router->dispatch(new Request());

        $this->assertFalse($handlerExecuted, 'Handler should not execute once middleware short-circuits.');
        $this->assertSame(403, $response->getStatus());
    }

    public function test_route_matches_when_request_has_trailing_slash(): void
    {
        $_SERVER['REQUEST_URI'] = '/pipeline/';

        $router = new Router(new EventDispatcher());
        $router->get('/pipeline', fn (Request $request): Response => Response::html('ok'));

        $response = $router->dispatch(new Request());

        $this->assertSame(200, $response->getStatus());
    }
}
