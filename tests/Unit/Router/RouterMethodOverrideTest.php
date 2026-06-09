<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Router;

use Anvyr\Loom\Core\EventDispatcher;
use Anvyr\Loom\Http\Response;
use Anvyr\Loom\Http\Routing\Router;
use Anvyr\Loom\Tests\Support\TestCase;

final class RouterMethodOverrideTest extends TestCase
{
    public function test_post_with_method_spoofing_hits_put_route(): void
    {
        $router = new Router(new EventDispatcher());
        $router->put('/items/{id}', fn ($request, $id) => Response::html('updated:' . $id));

        $request = $this->makeRequest('POST', '/items/42', ['_method' => 'PUT']);
        $response = $router->dispatch($request);

        $this->assertSame(200, $response->getStatus());
        $this->assertSame('updated:42', $response->getContent());
    }
}
