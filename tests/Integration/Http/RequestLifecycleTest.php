<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Integration\Http;

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\EventDispatcher;
use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;
use Anvyr\Loom\Http\Routing\Router;
use Anvyr\Loom\Tests\Support\Doubles\Http\RequestLifecycleTestController;
use Anvyr\Loom\Tests\Support\Doubles\Http\RequestLifecycleTestService;
use Anvyr\Loom\Tests\Support\TestCase;

final class RequestLifecycleTest extends TestCase
{
    private Application $app;
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Application($this->tmpDir);
        $events = new EventDispatcher();
        $this->app->instance('events', $events);
        $this->app->instance(EventDispatcher::class, $events);

        $this->router = new Router($events);
        $this->router->setApp($this->app);
    }

    public function test_basic_get_request_returns_response(): void
    {
        $this->router->get('/', function (Request $request) {
            return Response::html('<h1>Home</h1>');
        });

        $response = $this->router->dispatch($this->makeRequest('GET', '/'));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatus());
        $this->assertSame('<h1>Home</h1>', $response->getContent());
    }

    public function test_route_with_parameters(): void
    {
        $this->router->get('/user/{id}', function (Request $request, string $id) {
            return Response::json(['user_id' => $id]);
        });

        $response = $this->router->dispatch($this->makeRequest('GET', '/user/123'));

        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('"user_id":"123"', $response->getContent());
    }

    public function test_controller_action_with_autowiring(): void
    {
        $this->app->singleton(RequestLifecycleTestService::class, fn () => new RequestLifecycleTestService());

        $this->router->get('/test', [RequestLifecycleTestController::class, 'index']);

        $response = $this->router->dispatch($this->makeRequest('GET', '/test'));

        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('Service injected!', $response->getContent());
    }

    public function test_404_response_for_unmatched_route(): void
    {
        $this->router->get('/exists', function (Request $request) {
            return Response::html('Found');
        });

        $response = $this->router->dispatch($this->makeRequest('GET', '/does-not-exist'));

        $this->assertSame(404, $response->getStatus());
    }

    public function test_string_response_converted_to_html_response(): void
    {
        $this->router->get('/string', function (Request $request) {
            return 'Plain string response';
        });

        $response = $this->router->dispatch($this->makeRequest('GET', '/string'));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatus());
        $this->assertSame('Plain string response', $response->getContent());
    }

    public function test_array_response_converted_to_json_response(): void
    {
        $this->router->get('/array', function (Request $request) {
            return ['status' => 'ok', 'data' => [1, 2, 3]];
        });

        $response = $this->router->dispatch($this->makeRequest('GET', '/array'));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatus());
        $this->assertStringContainsString('"status":"ok"', $response->getContent());
    }
}
