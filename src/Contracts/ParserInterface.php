<?php

declare(strict_types=1);

namespace Anvyr\Loom\Contracts;

interface ParserInterface
{
    public function parse(string $content): string;
}
