<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support\Concerns;

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Core\Tenancy\TenancyState;
use Anvyr\Loom\Core\Tenancy\TenantContext;

trait TenancyTestHelpers
{
    protected function setTenancyConfig(array $config): void
    {
        $this->tenancyState()->setConfig($config);
    }

    protected function resetTenancyState(): void
    {
        $state = $this->tenancyState();
        $state->setConfig([]);
        $state->setCurrent(null);
    }

    protected function setCurrentTenant(string $id): void
    {
        $this->tenancyState()->setCurrent(new TenantContext($id));
    }

    private function tenancyState(): TenancyState
    {
        /** @var TenancyState $state */
        $state = Application::getInstance()->make(TenancyState::class);

        return $state;
    }
}
