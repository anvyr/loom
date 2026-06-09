<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Scheduling;

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Scheduling\Task;
use Anvyr\Loom\Tests\Support\Concerns\ReflectionHelpers;
use Anvyr\Loom\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class TaskTest extends TestCase
{
    use ReflectionHelpers;

    // === Cron Expression: Wildcard ===

    public function test_wildcard_always_matches(): void
    {
        $task = new Task(fn () => null);
        $task->everyMinute(); // * * * * *

        $this->assertTrue($task->isDue());
    }

    public function test_expression_is_due_when_current_time_matches(): void
    {
        foreach ($this->dueExpressions() as $label => $expression) {
            $task = $this->createTaskWithExpression($expression);

            $this->assertTrue($task->isDue(), $label);
        }
    }

    public function test_expression_is_not_due_when_current_time_does_not_match(): void
    {
        foreach ($this->nonDueExpressions() as $label => $expression) {
            $task = $this->createTaskWithExpression($expression);

            $this->assertFalse($task->isDue(), $label);
        }
    }

    // === Cron Expression: Step Values ===

    public function test_step_values_match_expected_due_state(): void
    {
        foreach ($this->stepExpressions() as $label => [$expression, $expected]) {
            $task = $this->createTaskWithExpression($expression);

            $this->assertSame($expected, $task->isDue(), $label);
        }
    }

    // === Cron Expression: Invalid ===

    #[DataProvider('provide_invalid_expressions')]
    public function test_invalid_expressions_return_false(string $expression): void
    {
        $task = $this->createTaskWithExpression($expression);

        $this->assertFalse($task->isDue());
    }

    // === Frequency Methods ===

    public function test_every_minute_sets_correct_expression(): void
    {
        $task = new Task(fn () => null);
        $task->everyMinute();

        // Every minute should always be due
        $this->assertTrue($task->isDue());
    }

    public function test_hourly_sets_correct_expression(): void
    {
        $task = new Task(fn () => null);
        $task->hourly();

        // Hourly = 0 * * * * (minute 0 of every hour)
        $minute = (int) date('i');
        $this->assertSame($minute === 0, $task->isDue());
    }

    public function test_daily_sets_correct_expression(): void
    {
        $task = new Task(fn () => null);
        $task->daily();

        // Daily = 0 0 * * * (midnight)
        $minute = (int) date('i');
        $hour = (int) date('H');
        $this->assertSame($minute === 0 && $hour === 0, $task->isDue());
    }

    public function test_daily_at_sets_correct_time(): void
    {
        $task = new Task(fn () => null);
        $hour = (int) date('H');
        $minute = (int) date('i');
        $task->dailyAt($hour, $minute);

        $this->assertTrue($task->isDue());
    }

    public function test_daily_at_with_different_time(): void
    {
        $task = new Task(fn () => null);
        $wrongHour = ((int) date('H') + 1) % 24;
        $task->dailyAt($wrongHour, 30);

        $this->assertFalse($task->isDue());
    }

    // === Command Tasks ===

    public function test_command_factory_creates_task(): void
    {
        $task = Task::command('cache:clear', ['--force' => true]);

        $this->assertSame('cache:clear', $task->getCommand());
        $this->assertSame(['--force' => true], $task->getParameters());
    }

    public function test_callback_task_has_null_command(): void
    {
        $task = new Task(fn () => 'result');

        $this->assertNull($task->getCommand());
    }

    // === Task Execution ===

    public function test_callback_task_runs_callback(): void
    {
        $ran = false;
        $task = new Task(function () use (&$ran) {
            $ran = true;
        });

        $task->run(new Application($this->tmpDir));

        $this->assertTrue($ran);
    }

    public function test_callback_receives_parameters(): void
    {
        $received = null;
        $task = new Task(
            function ($a, $b) use (&$received) {
                $received = [$a, $b];
            },
            ['first', 'second']
        );

        $task->run(new Application($this->tmpDir));

        $this->assertSame(['first', 'second'], $received);
    }

    // === Fluent Interface ===

    public function test_frequency_methods_return_self(): void
    {
        $task = new Task(fn () => null);

        $this->assertSame($task, $task->everyMinute());
        $this->assertSame($task, $task->hourly());
        $this->assertSame($task, $task->daily());
        $this->assertSame($task, $task->dailyAt(12, 0));
    }

    /**
     * Helper to create a task with a specific cron expression.
     */
    private function createTaskWithExpression(string $expression): Task
    {
        $task = new Task(fn () => null);
        $this->setPrivateProperty($task, 'expression', $expression);

        return $task;
    }

    private function dueExpressions(): array
    {
        $now = new \DateTimeImmutable();
        $minute = (int) $now->format('i');
        $hour = (int) $now->format('H');
        $day = (int) $now->format('d');
        $month = (int) $now->format('m');
        $weekday = (int) $now->format('w');
        $otherMinute = ($minute + 5) % 60;

        return [
            'exact minute' => "{$minute} * * * *",
            'exact hour' => "{$minute} {$hour} * * *",
            'comma list' => "{$minute},{$otherMinute} * * * *",
            'current day of month' => "{$minute} {$hour} {$day} * *",
            'current month' => "{$minute} {$hour} {$day} {$month} *",
            'current weekday' => "{$minute} {$hour} * * {$weekday}",
        ];
    }

    private function nonDueExpressions(): array
    {
        $now = new \DateTimeImmutable();
        $minute = (int) $now->format('i');
        $hour = (int) $now->format('H');
        $wrongMinute = ($minute + 1) % 60;
        $wrongHour = ($hour + 1) % 24;
        $otherMinuteOne = ($minute + 1) % 60;
        $otherMinuteTwo = ($minute + 2) % 60;
        $day = (int) $now->format('d');
        $wrongDay = ($day % 28) + 1;

        if ($wrongDay === $day) {
            $wrongDay = ($wrongDay % 28) + 1;
        }

        return [
            'wrong minute' => "{$wrongMinute} * * * *",
            'wrong hour' => "{$minute} {$wrongHour} * * *",
            'unlisted comma value' => "{$otherMinuteOne},{$otherMinuteTwo} * * * *",
            'wrong day of month' => "{$minute} {$hour} {$wrongDay} * *",
        ];
    }

    private function stepExpressions(): array
    {
        $minute = (int) (new \DateTimeImmutable())->format('i');

        return [
            'every minute' => ['*/1 * * * *', true],
            'every two minutes' => ['*/2 * * * *', $minute % 2 === 0],
            'every five minutes' => ['*/5 * * * *', $minute % 5 === 0],
            'every fifteen minutes' => ['*/15 * * * *', $minute % 15 === 0],
        ];
    }

    public static function provide_invalid_expressions(): array
    {
        return [
            'too few parts' => ['* * *'],
            'too many parts' => ['* * * * * *'],
        ];
    }
}
