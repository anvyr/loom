<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Core;

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Tests\Support\TestCase;
use RuntimeException;

final class ApplicationTest extends TestCase
{
    public function test_autowires_concrete_classes(): void
    {
        $app = new Application($this->tmpDir);
        $instance = $app->make(DummyService::class);
        $this->assertInstanceOf(DummyService::class, $instance);
    }

    public function test_unknown_service_throws(): void
    {
        $app = new Application($this->tmpDir);
        $this->expectException(RuntimeException::class);
        $app->make('missing');
    }
}

final class DummyDep
{
}

final class DummyService
{
    public function __construct(public DummyDep $dep = new DummyDep())
    {
    }
}
