<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Commands\Concerns;

use Anvyr\Loom\Commands\Command;
use Anvyr\Loom\Commands\Concerns\InteractsWithTenancy;
use Anvyr\Loom\Tests\Support\Concerns\TenancyTestHelpers;
use Anvyr\Loom\Tests\Support\TestCase;

final class InteractsWithTenancyTest extends TestCase
{
    use TenancyTestHelpers;
    protected function tearDown(): void
    {
        $this->resetTenancyState();
        parent::tearDown();
    }

    public function test_throws_when_tenancy_disabled(): void
    {
        $this->setTenancyConfig(['enabled' => false]);

        $command = $this->makeTenantCommand([], []);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenancy must be enabled');

        $command->callResolveTenantSelection();
    }

    public function test_returns_single_tenant_from_option(): void
    {
        $this->setTenancyConfig(['enabled' => true, 'paths' => ['user_root' => 'user/tenants']]);

        $command = $this->makeTenantCommand([], ['tenant' => 'acme']);

        $result = $command->callResolveTenantSelection();

        $this->assertSame(['acme'], $result);
    }

    public function test_throws_when_both_tenant_and_all_tenants_provided(): void
    {
        $this->setTenancyConfig(['enabled' => true, 'paths' => ['user_root' => 'user/tenants']]);

        $command = $this->makeTenantCommand([], ['tenant' => 'acme', 'all-tenants' => true]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Use either --tenant or --all-tenants, not both');

        $command->callResolveTenantSelection();
    }

    public function test_falls_back_to_current_tenant(): void
    {
        $this->setTenancyConfig(['enabled' => true, 'paths' => ['user_root' => 'user/tenants']]);
        $this->setCurrentTenant('current-one');

        $command = $this->makeTenantCommand([], []);

        $result = $command->callResolveTenantSelection(fallbackToCurrentTenant: true);

        $this->assertSame(['current-one'], $result);
    }

    public function test_returns_empty_when_no_current_and_no_fallback(): void
    {
        $this->setTenancyConfig(['enabled' => true, 'paths' => ['user_root' => 'user/tenants']]);

        $command = $this->makeTenantCommand([], []);

        $result = $command->callResolveTenantSelection(fallbackToCurrentTenant: false);

        $this->assertSame([], $result);
    }

    public function test_all_tenants_discovers_tenant_ids(): void
    {
        $relRoot = 'storage/test-tenants-' . bin2hex(random_bytes(4));
        $absRoot = base_path($relRoot);
        $this->mkdir($absRoot . '/alpha');
        $this->mkdir($absRoot . '/beta');

        $this->setTenancyConfig([
            'enabled' => true,
            'paths' => ['user_root' => $relRoot],
        ]);

        $command = $this->makeTenantCommand([], ['all-tenants' => true]);

        $result = $command->callResolveTenantSelection();

        $this->assertSame(['alpha', 'beta'], $result);

        $this->rrmdir($absRoot);
    }

    public function test_throws_when_all_tenants_not_allowed(): void
    {
        $this->setTenancyConfig(['enabled' => true, 'paths' => ['user_root' => 'user/tenants']]);

        $command = $this->makeTenantCommand([], ['all-tenants' => true]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('--all-tenants is not supported');

        $command->callResolveTenantSelection(allowAllTenants: false);
    }

    public function test_resolve_state_path_for_read_returns_existing_file(): void
    {
        $this->setTenancyConfig(['enabled' => false]);

        $storageDir = $this->tmpDir . '/storage';
        $this->mkdir($storageDir);
        file_put_contents($storageDir . '/modules.json', '{"enabled":[]}');

        $command = $this->makeTenantCommand([], []);

        $result = $command->callResolveStatePathForRead($this->tmpDir);

        $this->assertSame($storageDir . '/modules.json', $result);
    }

    public function test_resolve_state_path_for_read_returns_default_when_not_found(): void
    {
        $this->setTenancyConfig(['enabled' => false]);

        $command = $this->makeTenantCommand([], []);

        $result = $command->callResolveStatePathForRead($this->tmpDir);

        $this->assertStringEndsWith('/storage/modules.json', $result);
    }

    private function makeTenantCommand(array $arguments, array $options): TenantCommandStub
    {
        $command = new TenantCommandStub();
        $command->setArguments($arguments);
        $command->setOptions($options);

        return $command;
    }

}

/**
 * @internal
 */
class TenantCommandStub extends Command
{
    use InteractsWithTenancy;

    public function signature(): string
    {
        return 'test:tenant-stub [--tenant=] [--all-tenants]';
    }

    public function description(): string
    {
        return 'Stub for testing InteractsWithTenancy';
    }

    public function handle(): int
    {
        return self::SUCCESS;
    }

    public function callResolveTenantSelection(bool $allowAllTenants = true, bool $fallbackToCurrentTenant = true): array
    {
        return $this->resolveTenantSelection($allowAllTenants, $fallbackToCurrentTenant);
    }

    public function callResolveStatePathForRead(string $basePath): string
    {
        return $this->resolveStatePathForRead($basePath);
    }
}
