<?php

declare(strict_types=1);

namespace Anvyr\Loom\Tests\Unit\Scheduling;

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Scheduling\Schedule;
use Anvyr\Loom\Scheduling\Task;
use Anvyr\Loom\Tests\Support\TestCase;
use DateTime;

final class ScheduleTest extends TestCase
{
    public function test_call_registers_task_and_runs_callback(): void
    {
        $schedule = new Schedule();
        $ran = false;

        $task = $schedule->call(function () use (&$ran) {
            $ran = true;
        });

        $this->assertInstanceOf(Task::class, $task);
        $this->assertCount(1, $schedule->getAllTasks());

        $task->run(new Application($this->tmpDir));
        $this->assertTrue($ran);
    }

    public function test_get_due_tasks_filters_by_expression(): void
    {
        $schedule = new Schedule();
        $now = new DateTime();
        $hour = (int) $now->format('H');
        $minute = (int) $now->format('i');

        $schedule->call(fn () => null)->dailyAt($hour, $minute);

        $notDueMinute = ($minute + 1) % 60;
        $schedule->call(fn () => null)->dailyAt($hour, $notDueMinute);

        $due = $schedule->getDueTasks();
        $this->assertCount(1, $due);
    }

    public function test_command_tasks_preserve_command_and_parameters(): void
    {
        $schedule = new Schedule();
        $task = $schedule->command('cache:clear', ['--force' => true]);

        $this->assertSame('cache:clear', $task->getCommand());
        $this->assertSame(['--force' => true], $task->getParameters());
    }

    public function test_every_minute_tasks_are_due(): void
    {
        $task = new Task(fn () => null);
        $task->everyMinute();

        $this->assertTrue($task->isDue());
    }
}
