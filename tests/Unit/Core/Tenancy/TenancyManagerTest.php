<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Core\Tenancy;

use Anvyr\Loom\Contracts\TenantResolverInterface;
use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\Tenancy\TenancyManager;
use Anvyr\Loom\Core\Tenancy\TenantContext;
use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Tests\Support\Concerns\TenancyTestHelpers;
use Anvyr\Loom\Tests\Support\TestCase;

final class TenancyManagerTest extends TestCase
{
    use TenancyTestHelpers;
    protected function tearDown(): void
    {
        $this->resetTenancyState();
        parent::tearDown();
    }

    public function test_bootstrap_from_request_disabled_returns_null(): void
    {
        $this->setTenancyConfig(['enabled' => false]);

        $request = $this->makeRequest('GET', '/');
        $context = $this->manager()->bootstrapFromRequest($request);

        $this->assertNull($context);
        $this->assertNull($this->manager()->current());
    }

    public function test_host_mapping_resolves_tenant(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'resolver' => 'host',
            'host' => [
                'map' => [
                    'acme.test' => 'acme',
                ],
            ],
        ]);

        $request = $this->makeRequest('GET', '/', [], ['Host' => 'acme.test']);
        $context = $this->manager()->bootstrapFromRequest($request);

        $this->assertInstanceOf(TenantContext::class, $context);
        $this->assertSame('acme', $context->id());
        $this->assertSame('acme.test', $context->host());
        $this->assertSame('acme', $this->manager()->currentId());
    }

    public function test_path_resolver_sets_prefix_and_strips_request_path(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'resolver' => 'path',
            'path' => [
                'segment' => 1,
                'map' => [],
            ],
        ]);

        $request = $this->makeRequest('GET', '/tenant-x/docs');
        $context = $this->manager()->bootstrapFromRequest($request);

        $this->assertInstanceOf(TenantContext::class, $context);
        $this->assertSame('tenant-x', $context->id());
        $this->assertSame('/tenant-x', $context->pathPrefix());
        $this->assertSame('/docs', $request->path());
    }

    public function test_callback_resolver_uses_custom_resolver(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'resolver' => 'callback',
            'callback' => TestTenantResolver::class,
        ]);

        $request = $this->makeRequest('GET', '/');
        $context = $this->manager()->bootstrapFromRequest($request);

        $this->assertInstanceOf(TenantContext::class, $context);
        $this->assertSame('callback-tenant', $context->id());
        $this->assertSame(['source' => 'callback'], $context->metadata());
    }

    public function test_bootstrap_from_cli_uses_env_tenant(): void
    {
        $this->setTenancyConfig([
            'enabled' => true,
            'default' => 'default',
        ]);

        $_ENV['TENANCY_TENANT'] = 'cli-tenant';
        $_SERVER['TENANCY_TENANT'] = 'cli-tenant';
        putenv('TENANCY_TENANT=cli-tenant');

        try {
            $context = $this->manager()->bootstrapFromCli();
            $this->assertInstanceOf(TenantContext::class, $context);
            $this->assertSame('cli-tenant', $context->id());
        } finally {
            unset($_ENV['TENANCY_TENANT'], $_SERVER['TENANCY_TENANT']);
            putenv('TENANCY_TENANT');
        }
    }

    private function manager(): TenancyManager
    {
        /** @var TenancyManager $manager */
        $manager = Application::getInstance()->make(TenancyManager::class);

        return $manager;
    }

}

final class TestTenantResolver implements TenantResolverInterface
{
    public function resolve(Request $request, array $config): ?TenantContext
    {
        return new TenantContext('callback-tenant', null, null, null, ['source' => 'callback']);
    }
}
