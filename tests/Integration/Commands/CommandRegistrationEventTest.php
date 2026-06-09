<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Integration\Commands;

use Anvyr\Loom\Commands\CommandRegistry;
use Anvyr\Loom\Commands\ListCommand;
use Anvyr\Loom\Tests\Support\ApplicationTestCase;
use Anvyr\Loom\Tests\Support\Doubles\Commands\DemoCommand;

final class CommandRegistrationEventTest extends ApplicationTestCase
{
    public function test_modules_can_register_commands_via_event(): void
    {
        $app = $this->requireBootstrappedApplication();

        $registry = new CommandRegistry();
        $app->instance(CommandRegistry::class, $registry);
        $app->alias(CommandRegistry::class, 'commands');

        $events = $app->make('events');

        $received = null;
        $events->listen('commands.registering', function (CommandRegistry $commands) use (&$received): void {
            $received = $commands;
            $commands->register('demo:test', DemoCommand::class);
        });

        $events->dispatch('commands.registering', $registry);

        $this->assertSame($registry, $received);
        $this->assertTrue($registry->has('demo:test'));
    }

    public function test_container_resolves_list_command_with_bound_registry(): void
    {
        $app = $this->requireBootstrappedApplication();

        $registry = new CommandRegistry();
        $registry->register('version', \Anvyr\Loom\Commands\VersionCommand::class);
        $registry->register('list', ListCommand::class);

        $app->instance(CommandRegistry::class, $registry);
        $app->alias(CommandRegistry::class, 'commands');

        $command = $app->make(ListCommand::class);

        [$exitCode, $output] = $this->captureOutput(fn () => $command->handle());

        $this->assertSame(0, $exitCode, $output);
        $this->assertStringContainsString('version', $output);
    }
}
