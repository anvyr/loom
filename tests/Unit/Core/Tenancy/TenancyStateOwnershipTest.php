<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Core\Tenancy;

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\Tenancy\TenancyState;
use Anvyr\Loom\Core\Tenancy\TenantContext;
use Anvyr\Loom\Tests\Support\ApplicationTestCase;

final class TenancyStateOwnershipTest extends ApplicationTestCase
{
    public function test_application_binds_its_own_tenancy_state(): void
    {
        $state = new TenancyState(['enabled' => true, 'default' => 'alpha'], new TenantContext('alpha'));

        $app = new Application($this->sandboxPath(), $this->buildConfigRepository(), $state);

        $this->assertSame($state, $app->make(TenancyState::class));
        $this->assertSame('alpha', $state->currentId());
        $this->assertTrue($state->isEnabled());
    }

    public function test_tenant_binding_returns_live_context_instead_of_snapshot(): void
    {
        $app = $this->makeApplication();

        /** @var TenancyState $state */
        $state = $app->make(TenancyState::class);
        $state->setConfig(['enabled' => true]);
        $state->setCurrent(new TenantContext('first'));

        $first = $app->make('tenant');
        $this->assertInstanceOf(TenantContext::class, $first);
        $this->assertSame('first', $first->id());

        $state->setCurrent(new TenantContext('second'));

        $second = $app->make('tenant');
        $this->assertInstanceOf(TenantContext::class, $second);
        $this->assertSame('second', $second->id());
    }
}
