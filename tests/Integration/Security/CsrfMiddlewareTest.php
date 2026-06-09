<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Integration\Security;

use Anvyr\Loom\Core\EventDispatcher;
use Anvyr\Loom\Http\Middleware\VerifyCsrfToken;
use Anvyr\Loom\Http\Response;
use Anvyr\Loom\Http\Routing\Router;
use Anvyr\Loom\Tests\Support\TestCase;

final class CsrfMiddlewareTest extends TestCase
{
    public function test_missing_token_returns_419(): void
    {
        $router = new Router(new EventDispatcher());
        $router->registerMiddleware('csrf', VerifyCsrfToken::class);
        $router->pushMiddleware('csrf');
        $router->post('/submit', fn () => Response::html('ok'));

        $request = $this->makeRequest('POST', '/submit');
        $response = $router->dispatch($request);

        $this->assertSame(419, $response->getStatus());
    }

    public function test_valid_token_allows_request(): void
    {
        $router = new Router(new EventDispatcher());
        $router->registerMiddleware('csrf', VerifyCsrfToken::class);
        $router->pushMiddleware('csrf');
        $router->post('/submit', fn () => Response::html('ok'));

        $token = csrf_token();
        $request = $this->makeRequest('POST', '/submit', ['_token' => $token]);

        $response = $router->dispatch($request);
        $this->assertSame(200, $response->getStatus());
        $this->assertSame('ok', $response->getContent());
    }
}
