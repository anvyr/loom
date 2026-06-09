<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Services;

use Anvyr\Loom\Tests\Support\ViewEngineTestCase;
use RuntimeException;

final class ViewEngineLayoutTest extends ViewEngineTestCase
{
    public function test_includes_and_extends(): void
    {
        $this->writeView('layouts.base', "<title>@yield('title')</title>{{ \$content }}");
        $this->writeView('page', "@extends('layouts.base') @section('title')Page@endsection Body");

        $output = $this->engine->render('page');

        $this->assertStringContainsString('<title>Page</title>', $output);
        $this->assertStringContainsString('Body', $output);
    }

    public function test_yield_with_default_value(): void
    {
        $this->writeView('layouts.app', "@yield('sidebar', 'Default Sidebar')");
        $this->writeView('no-sidebar', "@extends('layouts.app')");

        $output = $this->engine->render('no-sidebar');

        $this->assertSame('Default Sidebar', $output);
    }

    public function test_section_overrides_yield_default(): void
    {
        $this->writeView('layouts.app', "@yield('sidebar', 'Default')");
        $this->writeView('with-sidebar', "@extends('layouts.app') @section('sidebar')Custom@endsection");

        $output = $this->engine->render('with-sidebar');

        $this->assertSame('Custom', $output);
    }

    public function test_throws_for_missing_layout(): void
    {
        $this->writeView('orphan', "@extends('layouts.missing')");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Layout 'layouts/missing' not found");

        $this->engine->render('orphan');
    }

    public function test_include_partial(): void
    {
        $this->writeView('partials.button', '<button>{{ $label }}</button>');
        $this->writeView('form', "@include('partials.button', ['label' => 'Submit'])");

        $output = $this->engine->render('form');

        $this->assertSame('<button>Submit</button>', $output);
    }

    public function test_include_inherits_parent_data(): void
    {
        $this->writeView('partials.greeting', 'Hello, {{ $name }}!');
        $this->writeView('wrapper', "@include('partials.greeting')");

        $output = $this->engine->render('wrapper', ['name' => 'World']);

        $this->assertSame('Hello, World!', $output);
    }

    public function test_push_and_stack(): void
    {
        $this->writeView('layouts.app', "<head>@stack('styles')</head><body>{{ \$content }}@stack('scripts')</body>");
        $this->writeView(
            'page',
            "@extends('layouts.app')@push('styles')<link rel=\"stylesheet\" href=\"/page.css\">@endpush@push('scripts')<script src=\"/page.js\"></script>@endpush Content",
        );

        $output = $this->engine->render('page');

        $this->assertStringContainsString('<head><link rel="stylesheet" href="/page.css"></head>', $output);
        $this->assertStringContainsString('<script src="/page.js"></script></body>', $output);
        $this->assertStringContainsString('Content', $output);
    }

    public function test_multiple_pushes_to_same_stack(): void
    {
        $this->writeView('layouts.base', "@stack('scripts')");
        $this->writeView('multi', "@extends('layouts.base')@push('scripts')A@endpush@push('scripts')B@endpush");

        $output = $this->engine->render('multi');

        $this->assertSame("A\nB", $output);
    }

    public function test_empty_stack_outputs_nothing(): void
    {
        $this->writeView('empty-stack', "before@stack('nothing')after");

        $output = $this->engine->render('empty-stack');

        $this->assertSame('beforeafter', $output);
    }

    public function test_push_from_included_partial(): void
    {
        $this->writeView('layouts.main', "<head>@stack('scripts')</head>@yield('content')");
        $this->writeView('partials.widget', "@push('scripts')<script src=\"/widget.js\"></script>@endpush<div>Widget</div>");
        $this->writeView('page-with-partial', "@extends('layouts.main')@section('content')@include('partials.widget')@endsection");

        $output = $this->engine->render('page-with-partial');

        $this->assertStringContainsString('<script src="/widget.js"></script>', $output);
        $this->assertStringContainsString('<div>Widget</div>', $output);
    }
}
