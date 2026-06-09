<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Validation;

use Anvyr\Loom\Exceptions\ValidationException;
use Anvyr\Loom\Tests\Support\ValidationTestCase;
use Anvyr\Loom\Validation\ValidationExtensionRegistry;

final class ValidatorExtensionTest extends ValidationTestCase
{
    public function test_extend_with_boolean_return(): void
    {
        $this->extensions()->extend('lowercase', fn ($value) => $value === strtolower($value));

        $validated = $this->validateData(
            ['name' => 'hello'],
            ['name' => 'lowercase'],
        );

        $this->assertSame('hello', $validated['name']);
    }

    public function test_extend_fails_with_false(): void
    {
        $this->extensions()->extend('lowercase', fn ($value) => $value === strtolower($value));

        $this->assertValidationFails(['name' => 'HELLO'], ['name' => 'lowercase']);
    }

    public function test_extend_with_custom_message(): void
    {
        $this->extensions()->extend('slug', function ($value, $parameter, $data, $field) {
            if (!preg_match('/^[a-z0-9-]+$/', $value)) {
                return "The {$field} must be a valid slug (lowercase letters, numbers, hyphens).";
            }

            return true;
        });

        $this->assertValidationFails(
            ['url' => 'Not A Slug!'],
            ['url' => 'slug'],
            function (ValidationException $exception): void {
                $this->assertStringContainsString('valid slug', $exception->getErrors()['url'][0]);
            },
        );
    }

    public function test_extend_with_parameter(): void
    {
        $this->extensions()->extend('divisible', fn ($value, $param) => $value % (int) $param === 0);

        $validated = $this->validateData(['num' => 10], ['num' => 'divisible:5']);
        $this->assertSame(10, $validated['num']);

        $this->assertValidationFails(['num' => 7], ['num' => 'divisible:3']);
    }

    public function test_extend_can_access_other_fields(): void
    {
        $this->extensions()->extend('greater_than', function ($value, $param, $data) {
            return $value > ($data[$param] ?? 0);
        });

        $validated = $this->validateData(
            ['min' => 5, 'max' => 10],
            ['max' => 'greater_than:min'],
        );

        $this->assertSame(10, $validated['max']);
    }

    public function test_has_extension(): void
    {
        $this->extensions()->extend('custom_rule', fn () => true);

        $this->assertTrue($this->extensions()->has('custom_rule'));
        $this->assertFalse($this->extensions()->has('nonexistent'));
    }

    private function extensions(): ValidationExtensionRegistry
    {
        /** @var ValidationExtensionRegistry $registry */
        $registry = app(ValidationExtensionRegistry::class);

        return $registry;
    }
}
