<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Validation;

use Anvyr\Loom\Tests\Support\ApplicationTestCase;
use Anvyr\Loom\Validation\ValidationExtensionRegistry;
use Anvyr\Loom\Validation\Validator;

final class ValidationExtensionRegistryOwnershipTest extends ApplicationTestCase
{
    public function test_application_reuses_preseeded_validation_extensions(): void
    {
        app(ValidationExtensionRegistry::class)->extend('lowercase', fn ($value) => $value === strtolower($value));

        $app = $this->makeApplication();

        /** @var ValidationExtensionRegistry $registry */
        $registry = $app->make(ValidationExtensionRegistry::class);

        $this->assertTrue($registry->has('lowercase'));
        $this->assertSame(['name' => 'hello'], Validator::make(['name' => 'hello'], ['name' => 'lowercase'])->validate());
    }

    public function test_validator_uses_app_bound_extension_registry(): void
    {
        $app = $this->makeApplication();

        /** @var ValidationExtensionRegistry $registry */
        $registry = $app->make(ValidationExtensionRegistry::class);
        $registry->extend('even', fn ($value) => is_int($value) && $value % 2 === 0);

        $this->assertTrue($registry->has('even'));
        $this->assertSame(['number' => 8], Validator::make(['number' => 8], ['number' => 'even'])->validate());
    }
}
