<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Services\Parsers;

use Anvyr\Loom\Services\Parsers\CommonMarkParser;
use Anvyr\Loom\Tests\Support\TestCase;

final class CommonMarkTemplateExtensionTest extends TestCase
{
    private CommonMarkParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CommonMarkParser(['html_input' => 'allow']);
    }

    public function test_preserves_blade_echo_tags(): void
    {
        $input = 'Hello {{ $name }}!';
        $expected = '<p>Hello {{ $name }}!</p>' . "\n";

        $this->assertSame($expected, $this->parser->parse($input));
    }

    public function test_preserves_blade_unescaped_tags(): void
    {
        $input = 'Hello {!! $name !!}!';
        $expected = '<p>Hello {!! $name !!}!</p>' . "\n";

        $this->assertSame($expected, $this->parser->parse($input));
    }

    public function test_preserves_blade_tags_inside_other_content(): void
    {
        $input = 'Header' . "\n\n" . 'Value: {{ $value }}' . "\n\n" . 'Footer';
        $result = $this->parser->parse($input);

        $this->assertStringContainsString('{{ $value }}', $result);
        $this->assertStringContainsString('<p>Value: {{ $value }}</p>', $result);
    }

    public function test_blade_tags_do_not_break_markdown_parsing(): void
    {
        $input = '**Bold** {{ $variable }}';
        $result = $this->parser->parse($input);

        $this->assertStringContainsString('<strong>Bold</strong>', $result);
        $this->assertStringContainsString('{{ $variable }}', $result);
    }
}
