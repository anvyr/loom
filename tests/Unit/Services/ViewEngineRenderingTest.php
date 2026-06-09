<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Services;

use Anvyr\Loom\Tests\Support\ViewEngineTestCase;
use RuntimeException;

final class ViewEngineRenderingTest extends ViewEngineTestCase
{
    public function test_renders_simple_view(): void
    {
        $this->writeView('hello', 'Hello, {{ $name }}');

        $output = $this->engine->render('hello', ['name' => 'Loom']);

        $this->assertSame('Hello, Loom', $output);
    }

    public function test_renders_view_with_dot_notation(): void
    {
        $this->writeView('pages.home', 'Home Page');

        $output = $this->engine->render('pages.home');

        $this->assertSame('Home Page', $output);
    }

    public function test_throws_for_missing_view(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("View 'nonexistent' not found");

        $this->engine->render('nonexistent');
    }

    public function test_double_braces_escape_html(): void
    {
        $this->writeView('escape', '{{ $html }}');

        $output = $this->engine->render('escape', ['html' => '<script>alert("xss")</script>']);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function test_raw_braces_do_not_escape(): void
    {
        $this->writeView('raw', '{!! $html !!}');

        $output = $this->engine->render('raw', ['html' => '<strong>Bold</strong>']);

        $this->assertSame('<strong>Bold</strong>', $output);
    }

    public function test_escaped_braces_are_not_processed(): void
    {
        $this->writeView('literal', '@{{ $notParsed }}');

        $output = $this->engine->render('literal', ['notParsed' => 'value']);

        $this->assertSame('{{ $notParsed }}', $output);
    }

    public function test_blade_style_comments_are_stripped(): void
    {
        $this->writeView('comments', 'A{{-- hidden --}}B {{ $name }}');

        $output = $this->engine->render('comments', ['name' => 'X']);

        $this->assertSame('AB X', $output);
        $this->assertStringNotContainsString('hidden', $output);
    }

    public function test_shared_data_available_in_all_views(): void
    {
        $this->writeView('shared', '{{ $siteName }}');

        $this->engine->share('siteName', 'Anvyr Loom');
        $output = $this->engine->render('shared');

        $this->assertSame('Anvyr Loom', $output);
    }

    public function test_local_data_overrides_shared(): void
    {
        $this->writeView('override', '{{ $value }}');

        $this->engine->share('value', 'shared');
        $output = $this->engine->render('override', ['value' => 'local']);

        $this->assertSame('local', $output);
    }

    public function test_exists_returns_true_for_existing_view(): void
    {
        $this->writeView('exists', 'content');

        $this->assertTrue($this->engine->exists('exists'));
    }

    public function test_exists_returns_false_for_missing_view(): void
    {
        $this->assertFalse($this->engine->exists('missing'));
    }
}
