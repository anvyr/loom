<?php

declare(strict_types=1);

namespace Anvyr\Loom\Contracts;

interface ShouldBeUnique
{
    public function uniqueId(): string;
}
