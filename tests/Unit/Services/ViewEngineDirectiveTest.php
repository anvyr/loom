<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Services;

use Anvyr\Loom\Tests\Support\ViewEngineTestCase;
use RuntimeException;

final class ViewEngineDirectiveTest extends ViewEngineTestCase
{
    public function test_if_directive(): void
    {
        $this->writeView('if', '@if($show)Visible@endif');

        $this->assertSame('Visible', $this->engine->render('if', ['show' => true]));
        $this->assertSame('', $this->engine->render('if', ['show' => false]));
    }

    public function test_if_else_directive(): void
    {
        $this->writeView('ifelse', '@if($condition)Yes@else No@endif');

        $this->assertSame('Yes', $this->engine->render('ifelse', ['condition' => true]));
        $this->assertSame(' No', $this->engine->render('ifelse', ['condition' => false]));
    }

    public function test_elseif_directive(): void
    {
        $this->writeView('elseif', '@if($val === 1)One@elseif($val === 2)Two@else Other@endif');

        $this->assertSame('One', $this->engine->render('elseif', ['val' => 1]));
        $this->assertSame('Two', $this->engine->render('elseif', ['val' => 2]));
        $this->assertSame(' Other', $this->engine->render('elseif', ['val' => 3]));
    }

    public function test_foreach_directive(): void
    {
        $this->writeView('foreach', '@foreach($items as $item){{ $item }}@endforeach');

        $output = $this->engine->render('foreach', ['items' => ['A', 'B', 'C']]);

        $this->assertSame('ABC', $output);
    }

    public function test_for_directive(): void
    {
        $this->writeView('for', '@for($i = 0; $i < 3; $i++){{ $i }}@endfor');

        $output = $this->engine->render('for');

        $this->assertSame('012', $output);
    }

    public function test_while_directive(): void
    {
        $this->writeView('while', '@php $i = 0; @endphp@while($i < 3){{ $i }}@php $i++; @endphp@endwhile');

        $output = $this->engine->render('while');

        $this->assertSame('012', $output);
    }

    public function test_php_directive(): void
    {
        $this->writeView('php', '@php $x = 5; @endphp{{ $x }}');

        $output = $this->engine->render('php');

        $this->assertSame('5', $output);
    }

    public function test_csrf_directive(): void
    {
        $this->writeView('csrf', '@csrf');

        $output = $this->engine->render('csrf');

        $this->assertStringContainsString('_token', $output);
        $this->assertStringContainsString('hidden', $output);
    }

    public function test_method_directive(): void
    {
        $this->writeView('method', "@method('PUT')");

        $output = $this->engine->render('method');

        $this->assertStringContainsString('_method', $output);
        $this->assertStringContainsString('PUT', $output);
    }

    public function test_isset_directive(): void
    {
        $this->writeView('isset', '@isset($name)Hello {{ $name }}@endisset');

        $this->assertSame('Hello World', $this->engine->render('isset', ['name' => 'World']));
        $this->assertSame('', $this->engine->render('isset', []));
    }

    public function test_empty_directive(): void
    {
        $this->writeView('empty', '@empty($items)No items@endempty');

        $this->assertSame('No items', $this->engine->render('empty', ['items' => []]));
        $this->assertSame('', $this->engine->render('empty', ['items' => ['a']]));
    }

    public function test_unless_directive(): void
    {
        $this->writeView('unless', '@unless($hidden)Visible@endunless');

        $this->assertSame('Visible', $this->engine->render('unless', ['hidden' => false]));
        $this->assertSame('', $this->engine->render('unless', ['hidden' => true]));
    }

    public function test_custom_directive(): void
    {
        $this->engine->directive('upper', fn ($expr) => "<?php echo strtoupper({$expr}); ?>");
        $this->writeView('custom', '@upper($name)');

        $output = $this->engine->render('custom', ['name' => 'loom']);

        $this->assertSame('LOOM', $output);
    }

    public function test_custom_directive_with_multiple_arguments(): void
    {
        $this->engine->directive('repeat', fn ($expr) => "<?php echo str_repeat({$expr}); ?>");
        $this->writeView('repeat', "@repeat('x', 3)");

        $output = $this->engine->render('repeat');

        $this->assertSame('xxx', $output);
    }

    public function test_endpush_without_push_throws(): void
    {
        $this->writeView('bad-endpush', '@endpush');

        try {
            $this->engine->render('bad-endpush');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $exception) {
            $this->assertSame('@endpush without @push', $exception->getMessage());
        } finally {
            $this->clearViewOutputBuffers();
        }
    }

    public function test_endsection_without_section_throws(): void
    {
        $this->writeView('bad-endsection', '@endsection');

        try {
            $this->engine->render('bad-endsection');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $exception) {
            $this->assertSame('@endsection without @section', $exception->getMessage());
        } finally {
            $this->clearViewOutputBuffers();
        }
    }

    public function test_mismatched_section_and_push_throws(): void
    {
        $this->writeView('mismatch', "@section('content')@endpush");

        try {
            $this->engine->render('mismatch');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $exception) {
            $this->assertSame('@endpush without matching @push', $exception->getMessage());
        } finally {
            $this->clearViewOutputBuffers();
        }
    }

    public function test_isset_with_array_access(): void
    {
        $this->writeView('isset-array', '@isset($data["key"]){{ $data["key"] }}@endisset');

        $this->assertSame('value', $this->engine->render('isset-array', ['data' => ['key' => 'value']]));
        $this->assertSame('', $this->engine->render('isset-array', ['data' => []]));
    }

    public function test_empty_with_method_call(): void
    {
        $this->writeView('empty-method', '@empty($obj->items())Empty@endempty');

        $emptyObject = new class () {
            public function items(): array
            {
                return [];
            }
        };

        $filledObject = new class () {
            public function items(): array
            {
                return ['a'];
            }
        };

        $this->assertSame('Empty', $this->engine->render('empty-method', ['obj' => $emptyObject]));
        $this->assertSame('', $this->engine->render('empty-method', ['obj' => $filledObject]));
    }
}
