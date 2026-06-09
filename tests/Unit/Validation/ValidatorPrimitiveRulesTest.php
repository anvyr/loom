<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Validation;

use Anvyr\Loom\Tests\Support\ValidationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ValidatorPrimitiveRulesTest extends ValidationTestCase
{
    #[DataProvider('provideRequiredEmptyValues')]
    public function test_required_fails_for_empty_values(mixed $value): void
    {
        $this->assertValidationFails(['field' => $value], ['field' => 'required']);
    }

    public function test_required_passes_for_zero(): void
    {
        $validated = $this->validateData(['field' => 0], ['field' => 'required']);

        $this->assertSame(0, $validated['field']);
    }

    public function test_required_passes_for_false(): void
    {
        $validated = $this->validateData(['field' => false], ['field' => 'required']);

        $this->assertFalse($validated['field']);
    }

    public function test_email_passes_for_valid_email(): void
    {
        $validated = $this->validateData(
            ['email' => 'test@example.com'],
            ['email' => 'email'],
        );

        $this->assertSame('test@example.com', $validated['email']);
    }

    public function test_email_fails_for_invalid_email(): void
    {
        $this->assertValidationFails(['email' => 'not-an-email'], ['email' => 'email']);
    }

    public function test_url_passes_for_valid_url(): void
    {
        $validated = $this->validateData(
            ['website' => 'https://example.com/path?query=1'],
            ['website' => 'url'],
        );

        $this->assertSame('https://example.com/path?query=1', $validated['website']);
    }

    public function test_url_fails_for_invalid_url(): void
    {
        $this->assertValidationFails(['website' => 'not a url'], ['website' => 'url']);
    }

    #[DataProvider('provideNumericValues')]
    public function test_numeric_passes_for_numeric_values(mixed $value): void
    {
        $validated = $this->validateData(['num' => $value], ['num' => 'numeric']);

        $this->assertSame($value, $validated['num']);
    }

    public function test_numeric_fails_for_non_numeric(): void
    {
        $this->assertValidationFails(['num' => 'abc'], ['num' => 'numeric']);
    }

    #[DataProvider('provideIntegerValues')]
    public function test_integer_passes_for_integer_values(mixed $value): void
    {
        $validated = $this->validateData(['num' => $value], ['num' => 'integer']);

        $this->assertSame($value, $validated['num']);
    }

    public function test_integer_fails_for_float(): void
    {
        $this->assertValidationFails(['num' => 3.14], ['num' => 'integer']);
    }

    #[DataProvider('provideBooleanValues')]
    public function test_boolean_passes_for_supported_values(mixed $value): void
    {
        $validated = $this->validateData(['flag' => $value], ['flag' => 'boolean']);

        $this->assertSame($value, $validated['flag']);
    }

    public function test_boolean_fails_for_invalid(): void
    {
        $this->assertValidationFails(['flag' => 'yes'], ['flag' => 'boolean']);
    }

    public function test_alpha_passes_for_letters_only(): void
    {
        $validated = $this->validateData(['name' => 'John'], ['name' => 'alpha']);

        $this->assertSame('John', $validated['name']);
    }

    public function test_alpha_fails_for_numbers(): void
    {
        $this->assertValidationFails(['name' => 'John123'], ['name' => 'alpha']);
    }

    public function test_alphanumeric_passes_for_letters_and_numbers(): void
    {
        $validated = $this->validateData(['code' => 'ABC123'], ['code' => 'alphanumeric']);

        $this->assertSame('ABC123', $validated['code']);
    }

    public function test_alphanumeric_fails_for_special_chars(): void
    {
        $this->assertValidationFails(['code' => 'ABC-123'], ['code' => 'alphanumeric']);
    }

    #[DataProvider('provideDateValues')]
    public function test_date_passes_for_supported_formats(string $value): void
    {
        $validated = $this->validateData(['date' => $value], ['date' => 'date']);

        $this->assertSame($value, $validated['date']);
    }

    public function test_date_fails_for_invalid_date(): void
    {
        $this->assertValidationFails(['date' => 'not-a-date'], ['date' => 'date']);
    }

    public function test_array_passes_for_array(): void
    {
        $validated = $this->validateData(['items' => [1, 2, 3]], ['items' => 'array']);

        $this->assertSame([1, 2, 3], $validated['items']);
    }

    public function test_array_fails_for_non_array(): void
    {
        $this->assertValidationFails(['items' => 'not an array'], ['items' => 'array']);
    }

    public static function provideRequiredEmptyValues(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'whitespace' => ['   '],
            'empty array' => [[]],
        ];
    }

    public static function provideNumericValues(): array
    {
        return [
            'integer' => [42],
            'float' => [3.14],
            'numeric string' => ['123'],
        ];
    }

    public static function provideIntegerValues(): array
    {
        return [
            'integer' => [42],
            'zero' => [0],
            'string integer' => ['42'],
        ];
    }

    public static function provideBooleanValues(): array
    {
        return [
            'true' => [true],
            'false' => [false],
            'string true' => ['true'],
            'string false' => ['false'],
            'one' => [1],
            'zero' => [0],
            'string one' => ['1'],
            'string zero' => ['0'],
        ];
    }

    public static function provideDateValues(): array
    {
        return [
            'ymd' => ['2025-01-15'],
            'dmy' => ['15-01-2025'],
            'long form' => ['January 15, 2025'],
            'datetime' => ['2025-01-15 10:30:00'],
        ];
    }
}
