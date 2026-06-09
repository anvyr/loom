<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Support\Doubles\Commands;

use Anvyr\Loom\Commands\Command;

final class DemoCommand extends Command
{
    public function signature(): string
    {
        return 'demo:test';
    }

    public function description(): string
    {
        return 'Demo command used for testing registration events';
    }

    public function handle(): int
    {
        return 0;
    }
}
