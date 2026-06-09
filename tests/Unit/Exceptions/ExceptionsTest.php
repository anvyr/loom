<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Exceptions;

use Anvyr\Loom\Exceptions\HttpException;
use Anvyr\Loom\Exceptions\ModuleException;
use Anvyr\Loom\Exceptions\NotFoundException;
use PHPUnit\Framework\TestCase;

final class ExceptionsTest extends TestCase
{
    // === HttpException Tests ===

    public function test_http_exception_stores_status_code(): void
    {
        $exception = new HttpException(404, 'Not Found');

        $this->assertSame(404, $exception->status());
    }

    public function test_http_exception_stores_message(): void
    {
        $exception = new HttpException(500, 'Server Error');

        $this->assertSame('Server Error', $exception->getMessage());
    }

    public function test_http_exception_default_message(): void
    {
        $exception = new HttpException(403);

        $this->assertSame(403, $exception->status());
    }

    public function test_http_exception_stores_headers(): void
    {
        $exception = new HttpException(429, 'Too Many Requests', [
            'Retry-After' => '60',
            'X-RateLimit-Remaining' => '0',
        ]);

        $headers = $exception->headers();

        $this->assertSame('60', $headers['Retry-After']);
        $this->assertSame('0', $headers['X-RateLimit-Remaining']);
    }

    public function test_http_exception_default_empty_headers(): void
    {
        $exception = new HttpException(400);

        $this->assertSame([], $exception->headers());
    }

    // === NotFoundException Tests ===

    public function test_not_found_exception_has_404_status(): void
    {
        $exception = new NotFoundException('Page not found');

        $this->assertSame(404, $exception->status());
    }

    public function test_not_found_exception_is_http_exception(): void
    {
        $exception = new NotFoundException();

        $this->assertInstanceOf(HttpException::class, $exception);
    }

    public function test_not_found_exception_default_message(): void
    {
        $exception = new NotFoundException();

        $this->assertSame('Resource not found', $exception->getMessage());
    }

    public function test_not_found_exception_custom_message(): void
    {
        $exception = new NotFoundException('User with ID 123 not found');

        $this->assertSame('User with ID 123 not found', $exception->getMessage());
    }

    // === ModuleException Tests ===

    public function test_module_exception_stores_message(): void
    {
        $exception = new ModuleException('Module failed to load');

        $this->assertSame('Module failed to load', $exception->getMessage());
    }

    public function test_module_exception_is_exception(): void
    {
        $exception = new ModuleException('Error');

        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function test_module_exception_with_code(): void
    {
        $exception = new ModuleException('Error', 100);

        $this->assertSame(100, $exception->getCode());
    }

    public function test_module_exception_with_previous(): void
    {
        $previous = new \RuntimeException('Original error');
        $exception = new ModuleException('Module error', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
