<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Support;

use Anvyr\Loom\Tests\Support\TestCase;

final class HelpersTest extends TestCase
{
    public function test_slugify_basic(): void
    {
        $this->assertSame('hello-world', slugify('Hello world!'));
    }

    public function test_array_get_nested(): void
    {
        $data = ['a' => ['b' => ['c' => 5]]];
        $this->assertSame(5, array_get($data, 'a.b.c'));
        $this->assertSame('fallback', array_get($data, 'a.b.d', 'fallback'));
    }
}
