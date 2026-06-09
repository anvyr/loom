<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Services;

use Anvyr\Loom\Tests\Support\Concerns\CreatesContentParser;
use Anvyr\Loom\Tests\Support\TestCase;

final class ContentParserTest extends TestCase
{
    use CreatesContentParser;

    private function parser(): \Anvyr\Loom\Services\ContentParser
    {
        return $this->makeContentParser(['html_input' => 'allow']);
    }

    public function test_frontmatter_is_extracted(): void
    {
        $parser = $this->parser();
        $parsed = $parser->parse("---\ntitle: Post\n---\n\nHello *world*.");

        $this->assertSame(['title' => 'Post'], $parsed['frontmatter']);
        $this->assertStringContainsString('<em>world</em>', $parsed['html']);
        $this->assertSame('Hello *world*.', ltrim($parsed['body']));
    }

    public function test_blocks_are_rendered_as_markdown_by_default(): void
    {
        $parser = $this->parser();
        $content = "@markdown\n# Title\n@text\n<p>raw</p>";

        $html = $parser->parse($content)['html'];
        $this->assertStringContainsString('<h1>Title</h1>', $html);
        $this->assertStringContainsString('&lt;p&gt;raw&lt;/p&gt;', $html);
    }

    public function test_markdown_mode_ignores_directives(): void
    {
        $parser = $this->parser();
        $content = "@html\n<p>test</p>\n@endhtml";

        $parsed = $parser->parse($content, 'markdown');

        $this->assertStringContainsString('@html', $parsed['html']);
    }
}
