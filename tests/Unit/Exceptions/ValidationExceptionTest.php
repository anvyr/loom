<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Exceptions;

use Anvyr\Loom\Exceptions\ValidationException;
use Anvyr\Loom\Tests\Support\TestCase;

final class ValidationExceptionTest extends TestCase
{
    public function test_to_response_returns_json_when_requested(): void
    {
        $request = $this->makeRequest('POST', '/submit', [], ['Accept' => 'application/json']);
        $exception = new ValidationException(['name' => ['Required']]);

        $response = $exception->toResponse($request);

        $this->assertSame(422, $response->getStatus());
        $this->assertStringContainsString('application/json', $response->getHeader('Content-Type') ?? '');
        $this->assertStringContainsString('"errors"', $response->getContent());
    }

    public function test_to_response_returns_html_when_not_json(): void
    {
        $request = $this->makeRequest('POST', '/submit');
        $exception = new ValidationException(['email' => ['Invalid']]);

        $response = $exception->toResponse($request);

        $this->assertSame(422, $response->getStatus());
        $this->assertStringContainsString('text/html', $response->getHeader('Content-Type') ?? '');
        $this->assertStringContainsString('Validation Failed', $response->getContent());
    }
}
