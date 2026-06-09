<?php

declare(strict_types=1);

namespace Anvyr\Loom\Contracts;

use Anvyr\Loom\Http\Request;
use Anvyr\Loom\Http\Response;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
