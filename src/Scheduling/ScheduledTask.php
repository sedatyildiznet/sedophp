<?php

declare(strict_types=1);

namespace SedoPHP\Scheduling;

use DateTimeImmutable;
use SedoPHP\Cache\Cache;

final class ScheduledTask
{
    /** @var list<callable(DateTimeImmutable):bool> */
    private array $conditions = [];
    private ?int $lockSeconds = null;
    private string $description = 'scheduled task';

    public function __construct(private readonly mixed $callback)
    {
    }

    public function everyMinute(): self
    {
        return $this;
    }

    public function hourly(): self
    {
        $this->conditions[] = static fn (DateTimeImmutable $now): bool => $now->format('i') === '00';
        return $this;
    }

    public function daily(): self
    {
        return $this->dailyAt('00:00');
    }

    public function dailyAt(string $time): self
    {
        $this->conditions[] = static fn (DateTimeImmutable $now): bool => $now->format('H:i') === $time;
        return $this;
    }

    public function weeklyOn(int $dayOfWeek, string $time = '00:00'): self
    {
        $this->conditions[] = static fn (DateTimeImmutable $now): bool =>
            (int) $now->format('w') === $dayOfWeek && $now->format('H:i') === $time;
        return $this;
    }

    public function weekdays(): self
    {
        $this->conditions[] = static fn (DateTimeImmutable $now): bool => (int) $now->format('N') <= 5;
        return $this;
    }

    public function when(callable $condition): self
    {
        $this->conditions[] = $condition;
        return $this;
    }

    public function withoutOverlapping(int $seconds = 3600): self
    {
        $this->lockSeconds = max(1, $seconds);
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function isDue(DateTimeImmutable $now): bool
    {
        foreach ($this->conditions as $condition) {
            if (!$condition($now)) {
                return false;
            }
        }
        return true;
    }

    public function run(): void
    {
        $lockKey = 'schedule:' . hash('sha256', $this->description);
        if ($this->lockSeconds !== null && Cache::has($lockKey)) {
            return;
        }

        if ($this->lockSeconds !== null) {
            Cache::put($lockKey, true, $this->lockSeconds);
        }

        try {
            ($this->callback)();
        } finally {
            if ($this->lockSeconds !== null) {
                Cache::forget($lockKey);
            }
        }
    }
}
