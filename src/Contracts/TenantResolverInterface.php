<?php

declare(strict_types=1);

namespace Anvyr\Loom\Contracts;

use Anvyr\Loom\Core\Tenancy\TenantContext;
use Anvyr\Loom\Http\Request;

interface TenantResolverInterface
{
    /** @param array<string, mixed> $config */
    public function resolve(Request $request, array $config): ?TenantContext;
}
