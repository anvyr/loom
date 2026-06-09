<?php

declare(strict_types=1);

namespace Anvyr\Loom\Services\Parsers;

use Anvyr\Loom\Contracts\ParserInterface;

class ParserFactory
{
    /** @param array<string, mixed> $config */
    public function make(string $driver, array $config = []): ParserInterface
    {
        return match ($driver) {
            'commonmark' => $this->createCommonMark($config),
            'parsedown' => $this->createParsedown($config),
            'html', 'none' => new HtmlParser(),
            default => throw new \InvalidArgumentException("Unsupported parser driver: {$driver}"),
        };
    }

    /** @param array<string, mixed> $config */
    private function createCommonMark(array $config): ParserInterface
    {
        if (!class_exists(\League\CommonMark\MarkdownConverter::class)) {
            trigger_error("The 'commonmark' driver requires 'league/commonmark'. Falling back to html parser. Run: composer require league/commonmark", E_USER_NOTICE);
            return new HtmlParser();
        }

        return new CommonMarkParser($config);
    }

    /** @param array<string, mixed> $config */
    private function createParsedown(array $config): ParserInterface
    {
        if (!class_exists(\Parsedown::class)) {
            trigger_error("The 'parsedown' driver requires 'erusev/parsedown'. Falling back to html parser. Run: composer require erusev/parsedown", E_USER_NOTICE);
            return new HtmlParser();
        }

        return new ParsedownParser($config);
    }
}
