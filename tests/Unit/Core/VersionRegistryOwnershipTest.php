<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Core;

use Anvyr\Loom\Core\VersionRegistry;
use Anvyr\Loom\Tests\Support\ApplicationTestCase;

final class VersionRegistryOwnershipTest extends ApplicationTestCase
{
    public function test_application_binds_version_registry_from_active_config(): void
    {
        config(['version' => [
            'core' => [
                'version' => '9.9.9',
                'stability' => 'stable',
            ],
            'modules' => [
                'docs' => [
                    'version' => '1.2.3',
                ],
            ],
        ]]);

        $app = $this->makeApplication();

        /** @var VersionRegistry $registry */
        $registry = $app->make(VersionRegistry::class);

        $this->assertSame($registry, app(VersionRegistry::class));
        $this->assertSame('9.9.9', $registry->getVersion('core'));
        $this->assertSame('1.2.3', $registry->getVersion('docs'));
    }
}
