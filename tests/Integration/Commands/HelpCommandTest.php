<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Integration\Commands;

use Anvyr\Loom\Commands\CommandRegistry;
use Anvyr\Loom\Commands\HelpCommand;
use Anvyr\Loom\Commands\ListCommand;
use Anvyr\Loom\Commands\MigrateCommand;
use Anvyr\Loom\Tests\Support\TestCase;

final class HelpCommandTest extends TestCase
{
    public function test_help_command_displays_command_usage(): void
    {
        $registry = new CommandRegistry();
        $registry->register('list', ListCommand::class);
        $registry->register('help', HelpCommand::class);
        $registry->register('migrate', MigrateCommand::class);

        $command = new HelpCommand($registry);
        $command->setArguments(['migrate']);
        $command->setOptions([]);

        [$exitCode, $output] = $this->captureOutput(fn () => $command->handle());

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('migrate', $output);
        $this->assertStringContainsString('Usage:', $output);
        $this->assertStringContainsString('--path', $output);
        $this->assertStringContainsString('--force', $output);
    }
}
