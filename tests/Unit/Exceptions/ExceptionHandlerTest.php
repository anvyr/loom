<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Exceptions;

use Anvyr\Loom\Core\EventDispatcher;
use Anvyr\Loom\Exceptions\Handler;
use Anvyr\Loom\Exceptions\HttpException;
use Anvyr\Loom\Tests\Support\TestCase;
use Psr\Log\NullLogger;

final class ExceptionHandlerTest extends TestCase
{
    public function test_http_exception_renders_json_when_requested(): void
    {
        $handler = new Handler(new EventDispatcher(), new NullLogger());

        $request = $this->makeRequest('GET', '/api', [], ['Accept' => 'application/json']);
        $response = $handler->render(new HttpException(404, 'Not here'), $request);

        $this->assertSame(404, $response->getStatus());
        $this->assertStringContainsString('Not here', $response->getContent());
    }

    public function test_generic_exception_in_debug_returns_html_trace(): void
    {
        config(['app.debug' => true]);
        $handler = new Handler(new EventDispatcher(), new NullLogger());

        $request = $this->makeRequest('GET', '/page');
        $response = $handler->render(new \RuntimeException('boom'), $request);

        $this->assertSame(500, $response->getStatus());
        $this->assertStringContainsString('RuntimeException', $response->getContent());
    }
}
