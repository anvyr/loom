<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Core\Tenancy;

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\CoreServiceProvider;
use Anvyr\Loom\Tests\Support\Concerns\ReflectionHelpers;
use Anvyr\Loom\Tests\Support\Concerns\TenancyTestHelpers;
use Anvyr\Loom\Tests\Support\TestCase;

final class TenantDatabaseResolutionTest extends TestCase
{
    use ReflectionHelpers;
    use TenancyTestHelpers;

    private CoreServiceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new CoreServiceProvider(Application::getInstance());
    }

    protected function tearDown(): void
    {
        $this->resetTenancyState();
        parent::tearDown();
    }

    public function test_returns_unchanged_config_when_database_per_tenant_disabled(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'database' => ['enabled' => false],
        ]);

        $config = $this->baseDbConfig();
        $result = $this->resolve('tenant-1', $config);

        $this->assertSame($config, $result);
    }

    public function test_returns_unchanged_config_when_database_section_missing(): void
    {
        $this->setTenancyConfig(['enabled' => true]);

        $config = $this->baseDbConfig();
        $result = $this->resolve('tenant-1', $config);

        $this->assertSame($config, $result);
    }

    public function test_explicit_mapping_to_connection_name(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'database' => [
                'enabled' => true,
                'map' => ['acme' => 'mysql_acme'],
            ],
        ]);

        $config = $this->baseDbConfig();
        $config['connections']['mysql_acme'] = [
            'driver' => 'mysql',
            'database' => 'acme_database',
        ];

        $result = $this->resolve('acme', $config);

        $this->assertSame('mysql_acme', $result['default']);
    }

    public function test_explicit_mapping_to_unknown_connection_throws(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'database' => [
                'enabled' => true,
                'map' => ['acme' => 'nonexistent'],
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Tenant 'acme' mapped to unknown connection 'nonexistent'");

        $this->resolve('acme', $this->baseDbConfig());
    }

    public function test_explicit_mapping_to_config_array_merges(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'database' => [
                'enabled' => true,
                'map' => [
                    'acme' => ['database' => 'custom_acme_db', 'host' => 'acme.db.local'],
                ],
            ],
        ]);

        $config = $this->baseDbConfig();
        $result = $this->resolve('acme', $config);

        $this->assertSame('custom_acme_db', $result['connections']['mysql']['database']);
        $this->assertSame('acme.db.local', $result['connections']['mysql']['host']);
        $this->assertSame('root', $result['connections']['mysql']['username']);
    }

    public function test_pattern_based_database_name(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'database' => [
                'enabled' => true,
                'pattern' => 'loom_{tenant}',
                'map' => [],
            ],
        ]);

        $config = $this->baseDbConfig();
        $result = $this->resolve('acme', $config);

        $this->assertSame('loom_acme', $result['connections']['mysql']['database']);
    }

    public function test_pattern_with_custom_format(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'database' => [
                'enabled' => true,
                'pattern' => 'site_{tenant}_db',
                'map' => [],
            ],
        ]);

        $config = $this->baseDbConfig();
        $result = $this->resolve('client123', $config);

        $this->assertSame('site_client123_db', $result['connections']['mysql']['database']);
    }

    public function test_explicit_mapping_takes_precedence_over_pattern(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'database' => [
                'enabled' => true,
                'pattern' => 'loom_{tenant}',
                'map' => ['special' => ['database' => 'special_override']],
            ],
        ]);

        $config = $this->baseDbConfig();

        $regularResult = $this->resolve('regular', $config);
        $this->assertSame('loom_regular', $regularResult['connections']['mysql']['database']);

        $specialResult = $this->resolve('special', $config);
        $this->assertSame('special_override', $specialResult['connections']['mysql']['database']);
    }

    public function test_works_with_sqlite_connection(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'database' => [
                'enabled' => true,
                'pattern' => '/data/{tenant}.sqlite',
            ],
        ]);

        $config = [
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => 'sqlite',
                    'database' => '/data/default.sqlite',
                ],
            ],
        ];

        $result = $this->resolve('tenant-1', $config);

        $this->assertSame('/data/tenant-1.sqlite', $result['connections']['sqlite']['database']);
    }

    private function resolve(string $tenantId, array $config): array
    {
        return $this->callPrivateMethod($this->provider, 'resolveTenantDatabase', [$tenantId, $config]);
    }

    private function baseDbConfig(): array
    {
        return [
            'default' => 'mysql',
            'connections' => [
                'mysql' => [
                    'driver' => 'mysql',
                    'host' => '127.0.0.1',
                    'database' => 'loom',
                    'username' => 'root',
                    'password' => '',
                ],
            ],
        ];
    }
}
