<?php

declare(strict_types=1);

namespace SedoPHP\Scheduling;

use DateTimeImmutable;

final class Schedule
{
    /** @var list<ScheduledTask> */
    private array $tasks = [];

    public function call(callable $callback): ScheduledTask
    {
        $task = new ScheduledTask($callback);
        $this->tasks[] = $task;
        return $task;
    }

    public function runDue(?DateTimeImmutable $now = null): int
    {
        $now ??= new DateTimeImmutable('now');
        $count = 0;

        foreach ($this->tasks as $task) {
            if (!$task->isDue($now)) {
                continue;
            }

            $task->run();
            $count++;
        }

        return $count;
    }
}
