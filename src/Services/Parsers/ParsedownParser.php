<?php

declare(strict_types=1);

namespace Anvyr\Loom\Services\Parsers;

use Anvyr\Loom\Contracts\ParserInterface;
use RuntimeException;

class ParsedownParser implements ParserInterface
{
    /** @var object */
    private object $parser;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        if (!class_exists('Parsedown')) {
            throw new RuntimeException(
                "The 'parsedown' driver requires the 'erusev/parsedown' package.\n" .
                'Please run: composer require erusev/parsedown'
            );
        }

        $this->parser = new \Parsedown();

        if (isset($config['html_input']) && $config['html_input'] === 'strip') {
            $this->parser->setSafeMode(true);
        }

        $this->parser->setBreaksEnabled($config['breaks'] ?? true);
    }

    public function parse(string $content): string
    {
        $callback = [$this->parser, 'text'];
        if (!is_callable($callback)) {
            throw new RuntimeException('Parsedown parser is not callable.');
        }

        return (string) $callback($content);
    }
}
