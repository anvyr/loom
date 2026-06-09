<?php

declare(strict_types=1);

namespace Anvyr\Loom\Services\Parsers;

use Anvyr\Loom\Contracts\ParserInterface;

class HtmlParser implements ParserInterface
{
    public function parse(string $content): string
    {
        return $content;
    }
}
