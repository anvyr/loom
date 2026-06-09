<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Validation;

use Anvyr\Loom\Tests\Support\ValidationTestCase;

final class ValidatorConstraintRulesTest extends ValidationTestCase
{
    public function test_min_passes_for_sufficient_length(): void
    {
        $validated = $this->validateData(['name' => 'John'], ['name' => 'min:3']);

        $this->assertSame('John', $validated['name']);
    }

    public function test_min_fails_for_insufficient_length(): void
    {
        $this->assertValidationFails(['name' => 'Jo'], ['name' => 'min:3']);
    }

    public function test_max_passes_for_within_limit(): void
    {
        $validated = $this->validateData(['name' => 'John'], ['name' => 'max:10']);

        $this->assertSame('John', $validated['name']);
    }

    public function test_max_fails_for_exceeding_limit(): void
    {
        $this->assertValidationFails(['name' => 'John Doe Smith'], ['name' => 'max:5']);
    }

    public function test_min_max_work_on_arrays(): void
    {
        $validated = $this->validateData(
            ['items' => [1, 2, 3]],
            ['items' => 'array|min:2|max:5'],
        );

        $this->assertCount(3, $validated['items']);
    }

    public function test_in_passes_for_allowed_value(): void
    {
        $validated = $this->validateData(
            ['status' => 'active'],
            ['status' => 'in:active,pending,closed'],
        );

        $this->assertSame('active', $validated['status']);
    }

    public function test_in_fails_for_disallowed_value(): void
    {
        $this->assertValidationFails(
            ['status' => 'unknown'],
            ['status' => 'in:active,pending,closed'],
        );
    }

    public function test_regex_passes_for_matching_pattern(): void
    {
        $validated = $this->validateData(
            ['phone' => '123-456-7890'],
            ['phone' => 'regex:/^\d{3}-\d{3}-\d{4}$/'],
        );

        $this->assertSame('123-456-7890', $validated['phone']);
    }

    public function test_regex_fails_for_non_matching_pattern(): void
    {
        $this->assertValidationFails(
            ['phone' => '1234567890'],
            ['phone' => 'regex:/^\d{3}-\d{3}-\d{4}$/'],
        );
    }
}
