<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Services\Parsers;

use Anvyr\Loom\Services\Parsers\HtmlParser;
use Anvyr\Loom\Tests\Support\TestCase;

final class HtmlParserTest extends TestCase
{
    private HtmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new HtmlParser();
    }

    public function test_returns_content_unchanged(): void
    {
        $html = '<h1>Hello World</h1><p>This is a test.</p>';

        $result = $this->parser->parse($html);

        $this->assertSame($html, $result);
    }

    public function test_preserves_html_entities(): void
    {
        $html = '<p>&amp; &lt; &gt; &quot;</p>';

        $result = $this->parser->parse($html);

        $this->assertSame($html, $result);
    }

    public function test_preserves_script_tags(): void
    {
        $html = '<script>alert("test")</script>';

        $result = $this->parser->parse($html);

        $this->assertSame($html, $result);
    }

    public function test_preserves_style_tags(): void
    {
        $html = '<style>.class { color: red; }</style>';

        $result = $this->parser->parse($html);

        $this->assertSame($html, $result);
    }

    public function test_preserves_complex_nested_html(): void
    {
        $html = <<<HTML
<div class="container">
    <header>
        <nav>
            <ul>
                <li><a href="/">Home</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <article>
            <h1>Title</h1>
            <p>Content</p>
        </article>
    </main>
</div>
HTML;

        $result = $this->parser->parse($html);

        $this->assertSame($html, $result);
    }

    public function test_handles_empty_string(): void
    {
        $result = $this->parser->parse('');

        $this->assertSame('', $result);
    }

    public function test_preserves_special_characters(): void
    {
        $html = '<p>Special: © ® ™ € £ ¥</p>';

        $result = $this->parser->parse($html);

        $this->assertSame($html, $result);
    }

    public function test_preserves_data_attributes(): void
    {
        $html = '<div data-id="123" data-name="test" data-json=\'{"key":"value"}\'></div>';

        $result = $this->parser->parse($html);

        $this->assertSame($html, $result);
    }
}
