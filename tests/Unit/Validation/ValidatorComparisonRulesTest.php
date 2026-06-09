<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Validation;

use Anvyr\Loom\Exceptions\ValidationException;
use Anvyr\Loom\Tests\Support\ValidationTestCase;

final class ValidatorComparisonRulesTest extends ValidationTestCase
{
    public function test_same_passes_when_fields_match(): void
    {
        $validated = $this->validateData(
            ['password' => 'secret', 'password_confirmation' => 'secret'],
            ['password_confirmation' => 'same:password'],
        );

        $this->assertSame('secret', $validated['password_confirmation']);
    }

    public function test_same_fails_when_fields_differ(): void
    {
        $this->assertValidationFails(
            ['password' => 'secret', 'password_confirmation' => 'different'],
            ['password_confirmation' => 'same:password'],
        );
    }

    public function test_different_passes_when_fields_differ(): void
    {
        $validated = $this->validateData(
            ['old_password' => 'old', 'new_password' => 'new'],
            ['new_password' => 'different:old_password'],
        );

        $this->assertSame('new', $validated['new_password']);
    }

    public function test_different_fails_when_fields_match(): void
    {
        $this->assertValidationFails(
            ['old_password' => 'same', 'new_password' => 'same'],
            ['new_password' => 'different:old_password'],
        );
    }

    public function test_optional_field_skipped_when_missing(): void
    {
        $validated = $this->validateData(
            ['name' => 'John'],
            ['name' => 'required', 'email' => 'email'],
        );

        $this->assertArrayNotHasKey('email', $validated);
    }

    public function test_optional_field_validated_when_present(): void
    {
        $this->assertValidationFails(
            ['name' => 'John', 'email' => 'invalid'],
            ['name' => 'required', 'email' => 'email'],
        );
    }

    public function test_multiple_rules_all_pass(): void
    {
        $validated = $this->validateData(
            ['username' => 'john123'],
            ['username' => 'required|min:3|max:20|alphanumeric'],
        );

        $this->assertSame('john123', $validated['username']);
    }

    public function test_multiple_rules_first_failure_reports(): void
    {
        $this->assertValidationFails(
            ['username' => 'a'],
            ['username' => 'required|min:3|max:20'],
            function (ValidationException $exception): void {
                $errors = $exception->getErrors();

                $this->assertArrayHasKey('username', $errors);
                $this->assertStringContainsString('at least 3', $errors['username'][0]);
            },
        );
    }

    public function test_array_rule_syntax(): void
    {
        $validated = $this->validateData(
            ['email' => 'test@example.com'],
            ['email' => ['required', 'email']],
        );

        $this->assertSame('test@example.com', $validated['email']);
    }

    public function test_validation_exception_contains_all_errors(): void
    {
        $this->assertValidationFails(
            ['email' => '', 'age' => 'not-a-number'],
            ['email' => 'required|email', 'age' => 'required|integer'],
            function (ValidationException $exception): void {
                $errors = $exception->getErrors();

                $this->assertArrayHasKey('email', $errors);
                $this->assertArrayHasKey('age', $errors);
            },
        );
    }
}
