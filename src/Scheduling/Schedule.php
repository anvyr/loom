<?php

declare(strict_types=1);

namespace Anvyr\Loom\Scheduling;

class Schedule
{
    /** @var list<Task> */
    protected array $tasks = [];

    /** @param list<mixed> $parameters */
    public function call(callable $callback, array $parameters = []): Task
    {
        $task = new Task($callback, $parameters);
        $this->tasks[] = $task;
        return $task;
    }

    /** @param list<string> $parameters */
    public function command(string $command, array $parameters = []): Task
    {
        $task = Task::command($command, $parameters);
        $this->tasks[] = $task;
        return $task;
    }

    /** @return list<Task> */
    public function getDueTasks(): array
    {
        return array_values(array_filter($this->tasks, fn (Task $task) => $task->isDue()));
    }

    /** @return list<Task> */
    public function getAllTasks(): array
    {
        return $this->tasks;
    }
}
