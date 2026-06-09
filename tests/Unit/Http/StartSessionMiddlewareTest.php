<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Http;

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\ConfigRepository;
use Anvyr\Loom\Core\Paths;
use Anvyr\Loom\Core\Tenancy\TenancyState;
use Anvyr\Loom\Http\Middleware\StartSessionMiddleware;
use Anvyr\Loom\Http\Response;
use Anvyr\Loom\Tests\Support\TestCase;

final class StartSessionMiddlewareTest extends TestCase
{
    public function test_returns_response_and_calls_next(): void
    {
        $app = Application::getInstance();
        $middleware = new StartSessionMiddleware(
            $app->make(ConfigRepository::class),
            $app->make(Paths::class),
            $app->make(TenancyState::class),
        );
        $request = $this->makeRequest('GET', '/');

        $response = $middleware->handle($request, fn () => Response::html('ok'));

        $this->assertSame(200, $response->getStatus());
        $this->assertSame('ok', $response->getContent());
    }
}
