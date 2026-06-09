<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Commands;

use Anvyr\Loom\Commands\Command;
use Anvyr\Loom\Commands\CommandRegistry;
use Anvyr\Loom\Tests\Support\TestCase;
use InvalidArgumentException;

final class CommandRegistryTest extends TestCase
{
    public function test_register_requires_command_subclass(): void
    {
        $registry = new CommandRegistry();

        $this->expectException(InvalidArgumentException::class);
        $registry->register('bad', \stdClass::class);
    }

    public function test_grouped_excludes_hidden_and_sorts(): void
    {
        $registry = new CommandRegistry();
        $registry->register('alpha', DummyCommandA::class);
        $registry->register('beta', DummyCommandB::class, ['category' => 'Custom']);
        $registry->register('hidden', DummyCommandB::class, ['hidden' => true]);

        $groups = $registry->grouped();

        $this->assertArrayHasKey('Custom', $groups);
        $this->assertArrayHasKey('General', $groups);
        $this->assertArrayHasKey('alpha', $groups['General']);
        $this->assertArrayHasKey('beta', $groups['Custom']);
        $this->assertArrayNotHasKey('hidden', $groups['Custom'] ?? []);
    }

    public function test_parse_argv_reads_arguments_and_options(): void
    {
        $registry = new CommandRegistry();

        [$name, $args, $options] = $registry->parseArgv([
            'loom',
            'demo:run',
            'first',
            '--force',
            '--path=foo',
            '-v',
        ]);

        $this->assertSame('demo:run', $name);
        $this->assertSame(['first'], $args);
        $this->assertSame(['force' => true, 'path' => 'foo', 'v' => true], $options);
    }

    public function test_run_unknown_command_returns_error(): void
    {
        $registry = new CommandRegistry();

        [$result, $output] = $this->captureOutput(fn () => $registry->run('missing'));

        $this->assertSame(1, $result);
        $this->assertStringContainsString("Command 'missing' not found", $output);
    }

    public function test_run_executes_command(): void
    {
        $registry = new CommandRegistry();
        $registry->register('demo', DummyCommandA::class);

        $result = $registry->run('demo', ['arg1'], ['flag' => true]);

        $this->assertSame(7, $result);
    }
}

final class DummyCommandA extends Command
{
    public function handle(): int
    {
        return 7;
    }

    public function signature(): string
    {
        return 'dummy:a';
    }

    public function description(): string
    {
        return 'Dummy A';
    }
}

final class DummyCommandB extends Command
{
    public static function category(): string
    {
        return 'Custom';
    }

    public function handle(): int
    {
        return 0;
    }

    public function signature(): string
    {
        return 'dummy:b';
    }

    public function description(): string
    {
        return 'Dummy B';
    }
}
