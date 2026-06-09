<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Http;

use Anvyr\Loom\Exceptions\ValidationException;
use Anvyr\Loom\Tests\Support\TestCase;

final class RequestValidationTest extends TestCase
{
    public function test_required_and_email_rules_pass(): void
    {
        $request = $this->makeRequest('POST', '/submit', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
        ]);

        $validated = $request->validate([
            'name' => 'required|min:2',
            'email' => 'required|email',
        ]);

        $this->assertSame(['name' => 'Jane', 'email' => 'jane@example.com'], $validated);
    }

    public function test_validation_throws_on_missing_required(): void
    {
        $request = $this->makeRequest('POST', '/submit', [
            'email' => 'not-an-email',
        ]);

        $this->expectException(ValidationException::class);
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
        ]);
    }

    public function test_numeric_and_integer_rules(): void
    {
        $request = $this->makeRequest('POST', '/numbers', [
            'age' => '21',
            'score' => '3.14',
        ]);

        $validated = $request->validate([
            'age' => 'required|integer',
            'score' => 'required|numeric',
        ]);

        $this->assertSame(['age' => '21', 'score' => '3.14'], $validated);
    }
}
