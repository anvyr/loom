<?php

declare(strict_types=1);

namespace Anvyr\Loom\Core;

abstract class ServiceProvider
{
    public function __construct(
        protected Application $app
    ) {
    }

    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
