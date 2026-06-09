<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\External\Services\Parsers;

use Anvyr\Loom\Services\Parsers\ParsedownParser;
use Anvyr\Loom\Services\Parsers\ParserFactory;
use Anvyr\Loom\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('external')]
final class ParsedownParserFactoryTest extends TestCase
{
    public function test_creates_parsedown_parser_when_package_is_installed(): void
    {
        if (!class_exists('Parsedown')) {
            $this->markTestSkipped('Parsedown not installed.');
        }

        $parser = (new ParserFactory())->make('parsedown');

        $this->assertInstanceOf(ParsedownParser::class, $parser);
    }
}
