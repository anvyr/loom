<?php

declare(strict_types=1);

namespace Anvyr\Loom\Commands;

use Anvyr\Loom\Core\Application;
use Anvyr\Loom\Scheduling\Schedule;

class ScheduleRunCommand extends Command
{
    public function signature(): string
    {
        return 'schedule:run';
    }

    public function description(): string
    {
        return 'Run the scheduled tasks';
    }

    public function __construct(
        private readonly Application $app,
        private readonly Schedule $schedule
    ) {
    }

    public function handle(): int
    {
        $tasks = $this->schedule->getDueTasks();

        if (empty($tasks)) {
            $this->info('No tasks are due right now.');
            return 0;
        }

        foreach ($tasks as $task) {
            $this->runTask($task);
        }

        return 0;
    }

    protected function runTask(\Anvyr\Loom\Scheduling\Task $task): void
    {
        if ($cmd = $task->getCommand()) {
            $this->info("Running command: {$cmd}");
            $prefix = defined('LOOM_BINARY')
                ? LOOM_BINARY
                : (PHP_BINARY . ' ' . $this->app->basePath() . '/loom');

            $command = build_cli_command_prefix($prefix, $cmd);
            passthru($command);

        } else {
            $this->info('Running callback task...');
            $task->run($this->app);
        }
    }
}
