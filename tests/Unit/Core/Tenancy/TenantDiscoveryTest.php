<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Core\Tenancy;

use Anvyr\Loom\Core\Tenancy\TenantDiscovery;
use Anvyr\Loom\Tests\Support\Concerns\TenancyTestHelpers;
use Anvyr\Loom\Tests\Support\TestCase;

final class TenantDiscoveryTest extends TestCase
{
    use TenancyTestHelpers;
    private string $tenantRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantRoot = 'storage/test-tenants-' . bin2hex(random_bytes(4));
        $absoluteRoot = base_path($this->tenantRoot);

        $this->mkdir($absoluteRoot . '/alpha');
        $this->mkdir($absoluteRoot . '/beta');
        file_put_contents($absoluteRoot . '/README.txt', 'not a tenant');

        $this->setTenancyConfig([
            'enabled' => true,
            'paths' => [
                'user_root' => $this->tenantRoot,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->rrmdir(base_path($this->tenantRoot));
        $this->resetTenancyState();
        parent::tearDown();
    }

    public function test_discovers_tenant_directories_sorted(): void
    {
        $tenants = app(TenantDiscovery::class)->discoverTenantIds();

        $this->assertSame(['alpha', 'beta'], $tenants);
    }

}
