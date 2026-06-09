<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Services;

use Anvyr\Loom\Tests\Support\ViewEngineTestCase;
use RuntimeException;

final class ViewEngineCompilationTest extends ViewEngineTestCase
{
    public function test_compile_string_processes_template(): void
    {
        $output = $this->engine->compileString('Hello, {{ $name }}!', ['name' => 'Test']);

        $this->assertSame('Hello, Test!', $output);
    }

    public function test_compile_string_with_directives(): void
    {
        $output = $this->engine->compileString(
            '@foreach($items as $i){{ $i }}@endforeach',
            ['items' => [1, 2, 3]],
        );

        $this->assertSame('123', $output);
    }

    public function test_compile_string_can_be_disabled_by_configuration(): void
    {
        $this->withStringEvaluationDisabled(function (): void {
            $this->expectException(RuntimeException::class);
            $this->engine->compileString('Hello {{ $name }}', ['name' => 'World']);
        });
    }

    public function test_safe_strips_php_blocks(): void
    {
        $output = $this->engine->safe('@php echo "unsafe"; @endphp Safe content', []);

        $this->assertStringNotContainsString('unsafe', $output);
        $this->assertStringContainsString('Safe content', $output);
    }

    public function test_safe_converts_raw_to_escaped(): void
    {
        $output = $this->engine->safe('{!! $html !!}', ['html' => '<b>Bold</b>']);

        $this->assertStringNotContainsString('<b>', $output);
        $this->assertStringContainsString('&lt;b&gt;', $output);
    }

    public function test_safe_can_be_disabled_by_configuration(): void
    {
        $this->withStringEvaluationDisabled(function (): void {
            $this->expectException(RuntimeException::class);
            $this->engine->safe('Hello {{ $name }}', ['name' => 'World']);
        });
    }

    public function test_clears_cache(): void
    {
        $this->writeView('cached', 'content');
        $this->engine->render('cached');

        $cacheFiles = glob($this->cachePath('*.php'));
        $this->assertNotEmpty($cacheFiles);

        $this->engine->clearCache();

        $cacheFiles = glob($this->cachePath('*.php'));
        $this->assertEmpty($cacheFiles);
    }
}
