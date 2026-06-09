<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Core\Tenancy;

use Anvyr\Loom\Core\Tenancy\TenantContext;
use PHPUnit\Framework\TestCase;

final class TenantContextTest extends TestCase
{
    public function test_id_returns_tenant_id(): void
    {
        $context = new TenantContext('tenant-123');

        $this->assertSame('tenant-123', $context->id());
    }

    public function test_host_returns_configured_host(): void
    {
        $context = new TenantContext('tenant-1', host: 'tenant1.example.com');

        $this->assertSame('tenant1.example.com', $context->host());
    }

    public function test_host_returns_null_when_not_set(): void
    {
        $context = new TenantContext('tenant-1');

        $this->assertNull($context->host());
    }

    public function test_path_prefix_returns_configured_prefix(): void
    {
        $context = new TenantContext('tenant-1', pathPrefix: '/t/tenant-1');

        $this->assertSame('/t/tenant-1', $context->pathPrefix());
    }

    public function test_path_prefix_returns_null_when_not_set(): void
    {
        $context = new TenantContext('tenant-1');

        $this->assertNull($context->pathPrefix());
    }

    public function test_url_prefix_returns_configured_prefix(): void
    {
        $context = new TenantContext('tenant-1', urlPrefix: 'https://tenant1.example.com');

        $this->assertSame('https://tenant1.example.com', $context->urlPrefix());
    }

    public function test_url_prefix_returns_null_when_not_set(): void
    {
        $context = new TenantContext('tenant-1');

        $this->assertNull($context->urlPrefix());
    }

    public function test_metadata_returns_configured_metadata(): void
    {
        $metadata = [
            'name' => 'Tenant One',
            'plan' => 'premium',
            'features' => ['blog', 'shop'],
        ];

        $context = new TenantContext('tenant-1', metadata: $metadata);

        $this->assertSame($metadata, $context->metadata());
        $this->assertSame('Tenant One', $context->metadata()['name']);
    }

    public function test_metadata_returns_empty_array_when_not_set(): void
    {
        $context = new TenantContext('tenant-1');

        $this->assertSame([], $context->metadata());
    }

    public function test_all_properties_can_be_set(): void
    {
        $context = new TenantContext(
            id: 'full-tenant',
            host: 'full.example.com',
            pathPrefix: '/full',
            urlPrefix: 'https://full.example.com',
            metadata: ['key' => 'value']
        );

        $this->assertSame('full-tenant', $context->id());
        $this->assertSame('full.example.com', $context->host());
        $this->assertSame('/full', $context->pathPrefix());
        $this->assertSame('https://full.example.com', $context->urlPrefix());
        $this->assertSame(['key' => 'value'], $context->metadata());
    }
}
