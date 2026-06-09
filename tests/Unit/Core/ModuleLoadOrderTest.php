<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Core;

use Anvyr\Loom\Exceptions\ModuleException;
use Anvyr\Loom\Tests\Support\ModuleManagerTestCase;

final class ModuleLoadOrderTest extends ModuleManagerTestCase
{
    public function test_resolve_load_order_orders_dependencies(): void
    {
        $loadOrder = $this->moduleManager->resolveLoadOrder([
            'module-a' => [
                'name' => 'module-a',
                'version' => '1.0.0',
                'requires' => ['module-b' => '^1.0'],
            ],
            'module-b' => [
                'name' => 'module-b',
                'version' => '1.0.0',
                'requires' => [],
            ],
            'module-c' => [
                'name' => 'module-c',
                'version' => '1.0.0',
                'requires' => ['module-a' => '^1.0'],
            ],
        ]);

        $this->assertSame(['module-b', 'module-a', 'module-c'], $loadOrder);
    }

    public function test_resolve_load_order_detects_circular_dependency(): void
    {
        $this->expectException(ModuleException::class);
        $this->expectExceptionMessage('Circular dependency');

        $this->moduleManager->resolveLoadOrder([
            'module-a' => [
                'name' => 'module-a',
                'version' => '1.0.0',
                'requires' => ['module-b' => '^1.0'],
            ],
            'module-b' => [
                'name' => 'module-b',
                'version' => '1.0.0',
                'requires' => ['module-a' => '^1.0'],
            ],
        ]);
    }
}
