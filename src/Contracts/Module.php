<?php

declare(strict_types=1);

namespace Anvyr\Loom\Contracts;

use Anvyr\Loom\Core\Application;

interface Module
{
    public function register(Application $app): void;

    public function boot(Application $app): void;

    public function path(string $path = ''): string;
}
