<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Router;

use Anvyr\Loom\Core\EventDispatcher;
use Anvyr\Loom\Http\Response;
use Anvyr\Loom\Http\Routing\Router;
use Anvyr\Loom\Tests\Support\TestCase;

final class RouterGroupTest extends TestCase
{
    private function router(): Router
    {
        return new Router(new EventDispatcher());
    }

    public function test_group_applies_prefix(): void
    {
        $router = $this->router();

        $router->group(['prefix' => '/api'], function (Router $r) {
            $r->get('/users', fn () => Response::json(['users' => []]));
        });

        $response = $router->dispatch($this->makeRequest('GET', '/api/users'));

        $this->assertSame(200, $response->getStatus());
    }

    public function test_group_prefix_does_not_match_without_prefix(): void
    {
        $router = $this->router();

        $router->group(['prefix' => '/api'], function (Router $r) {
            $r->get('/users', fn () => Response::json(['users' => []]));
        });

        $response = $router->dispatch($this->makeRequest('GET', '/users'));

        $this->assertSame(404, $response->getStatus());
    }

    public function test_group_applies_middleware(): void
    {
        $router = $this->router();
        $log = [];

        $router->registerMiddleware('auth', function ($request, $next) use (&$log) {
            $log[] = 'auth';
            return $next($request);
        });

        $router->group(['middleware' => 'auth'], function (Router $r) {
            $r->get('/dashboard', fn () => Response::html('ok'));
        });

        $router->dispatch($this->makeRequest('GET', '/dashboard'));

        $this->assertSame(['auth'], $log);
    }

    public function test_group_combines_prefix_and_middleware(): void
    {
        $router = $this->router();
        $log = [];

        $router->registerMiddleware('throttle', function ($request, $next) use (&$log) {
            $log[] = 'throttle';
            return $next($request);
        });

        $router->group(['prefix' => '/api', 'middleware' => 'throttle'], function (Router $r) {
            $r->get('/posts', fn () => Response::json(['posts' => []]));
        });

        $response = $router->dispatch($this->makeRequest('GET', '/api/posts'));

        $this->assertSame(200, $response->getStatus());
        $this->assertSame(['throttle'], $log);
    }

    public function test_nested_groups_stack_prefixes(): void
    {
        $router = $this->router();

        $router->group(['prefix' => '/api'], function (Router $r) {
            $r->group(['prefix' => '/v1'], function (Router $r) {
                $r->get('/users', fn () => Response::json(['version' => 1]));
            });
        });

        $response = $router->dispatch($this->makeRequest('GET', '/api/v1/users'));

        $this->assertSame(200, $response->getStatus());
    }

    public function test_nested_groups_merge_middleware(): void
    {
        $router = $this->router();
        $log = [];

        $router->registerMiddleware('a', function ($request, $next) use (&$log) {
            $log[] = 'a';
            return $next($request);
        });
        $router->registerMiddleware('b', function ($request, $next) use (&$log) {
            $log[] = 'b';
            return $next($request);
        });

        $router->group(['middleware' => 'a'], function (Router $r) {
            $r->group(['middleware' => 'b'], function (Router $r) {
                $r->get('/nested', fn () => Response::html('ok'));
            });
        });

        $router->dispatch($this->makeRequest('GET', '/nested'));

        $this->assertSame(['a', 'b'], $log);
    }

    public function test_group_does_not_leak_to_routes_outside(): void
    {
        $router = $this->router();
        $log = [];

        $router->registerMiddleware('scoped', function ($request, $next) use (&$log) {
            $log[] = 'scoped';
            return $next($request);
        });

        $router->group(['prefix' => '/api', 'middleware' => 'scoped'], function (Router $r) {
            $r->get('/inside', fn () => Response::html('inside'));
        });

        $router->get('/outside', fn () => Response::html('outside'));

        // Outside route should not have prefix or middleware
        $response = $router->dispatch($this->makeRequest('GET', '/outside'));
        $this->assertSame(200, $response->getStatus());
        $this->assertSame('outside', $response->getContent());
        $this->assertSame([], $log);
    }

    public function test_route_middleware_stacks_on_top_of_group_middleware(): void
    {
        $router = $this->router();
        $log = [];

        $router->registerMiddleware('group-mw', function ($request, $next) use (&$log) {
            $log[] = 'group';
            return $next($request);
        });
        $router->registerMiddleware('route-mw', function ($request, $next) use (&$log) {
            $log[] = 'route';
            return $next($request);
        });

        $router->group(['middleware' => 'group-mw'], function (Router $r) {
            $r->get('/path', fn () => Response::html('ok'))->middleware('route-mw');
        });

        $router->dispatch($this->makeRequest('GET', '/path'));

        $this->assertSame(['group', 'route'], $log);
    }

    public function test_group_with_multiple_middleware(): void
    {
        $router = $this->router();
        $log = [];

        $router->registerMiddleware('x', function ($request, $next) use (&$log) {
            $log[] = 'x';
            return $next($request);
        });
        $router->registerMiddleware('y', function ($request, $next) use (&$log) {
            $log[] = 'y';
            return $next($request);
        });

        $router->group(['middleware' => ['x', 'y']], function (Router $r) {
            $r->get('/multi', fn () => Response::html('ok'));
        });

        $router->dispatch($this->makeRequest('GET', '/multi'));

        $this->assertSame(['x', 'y'], $log);
    }
}
