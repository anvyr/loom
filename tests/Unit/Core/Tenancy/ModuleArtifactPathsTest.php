<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Core\Tenancy;

use Anvyr\Loom\Core\Tenancy\ModuleArtifactPaths;
use Anvyr\Loom\Tests\Support\Concerns\TenancyTestHelpers;
use Anvyr\Loom\Tests\Support\TestCase;

final class ModuleArtifactPathsTest extends TestCase
{
    use TenancyTestHelpers;
    protected function tearDown(): void
    {
        $this->resetTenancyState();
        parent::tearDown();
    }

    public function test_uses_global_paths_when_tenancy_disabled(): void
    {
        $this->setTenancyConfig(['enabled' => false]);
        $artifactPaths = app(ModuleArtifactPaths::class);

        $this->assertSame(base_path('storage/modules.json'), $artifactPaths->statePath());
        $this->assertSame(base_path('storage/modules-compiled.json'), $artifactPaths->compiledPath());
        $this->assertSame(base_path('storage/modules-autoload.php'), $artifactPaths->autoloadPath());
    }

    public function test_uses_tenant_scoped_paths_when_enabled(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'paths' => [
                'storage_root' => 'storage/tenants',
            ],
        ]);

        $this->setCurrentTenant('tenant-a');
        $artifactPaths = app(ModuleArtifactPaths::class);

        $this->assertSame(base_path('storage/tenants/tenant-a/modules/modules.json'), $artifactPaths->statePath());
        $this->assertSame(base_path('storage/tenants/tenant-a/modules/modules-compiled.json'), $artifactPaths->compiledPath());
        $this->assertSame(base_path('storage/tenants/tenant-a/modules/modules-autoload.php'), $artifactPaths->autoloadPath());
    }

    public function test_compiled_candidates_are_tenant_first_then_global(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'paths' => [
                'storage_root' => 'storage/tenants',
            ],
        ]);

        $this->setCurrentTenant('tenant-b');

        $candidates = app(ModuleArtifactPaths::class)->compiledCandidates();

        $this->assertSame(base_path('storage/tenants/tenant-b/modules/modules-compiled.json'), $candidates[0]);
        $this->assertSame(base_path('storage/modules-compiled.json'), $candidates[1]);
    }

}
