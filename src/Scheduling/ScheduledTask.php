<?php

declare(strict_types=1);

namespace SedoPHP\Scheduling;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use SedoPHP\Cache\Cache;

final class ScheduledTask
{
    /** @var list<callable(DateTimeImmutable):bool> */
    private array $conditions = [];
    private ?int $lockSeconds = null;
    private string $description = 'scheduled task';
    private ?DateTimeZone $timezone = null;

    public function __construct(
        private readonly mixed $callback,
        private readonly string $identity = 'task-0',
    )
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

    public function timezone(string $timezone): self
    {
        $this->timezone = new DateTimeZone($timezone);
        return $this;
    }

    public function cron(string $expression): self
    {
        $parts = preg_split('/\s+/', trim($expression)) ?: [];
        if (count($parts) !== 5) {
            throw new InvalidArgumentException('Cron expression must contain five fields.');
        }
        $this->conditions[] = static function (DateTimeImmutable $now) use ($parts): bool {
            $values = [(int) $now->format('i'), (int) $now->format('G'), (int) $now->format('j'), (int) $now->format('n'), (int) $now->format('w')];
            $ranges = [[0, 59], [0, 23], [1, 31], [1, 12], [0, 7]];
            foreach ($parts as $index => $field) {
                if (!self::cronFieldMatches($field, $values[$index], $ranges[$index][0], $ranges[$index][1])) {
                    return false;
                }
            }
            return true;
        };
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
        if ($this->timezone !== null) {
            $now = $now->setTimezone($this->timezone);
        }
        foreach ($this->conditions as $condition) {
            if (!$condition($now)) {
                return false;
            }
        }
        return true;
    }

    private static function cronFieldMatches(string $field, int $value, int $min, int $max): bool
    {
        foreach (explode(',', $field) as $part) {
            $step = 1;
            if (str_contains($part, '/')) {
                [$part, $stepText] = explode('/', $part, 2);
                $step = (int) $stepText;
                if ($step < 1) {
                    throw new InvalidArgumentException('Cron step must be at least one.');
                }
            }
            $start = $min;
            $end = $max;
            if ($part !== '*') {
                if (str_contains($part, '-')) {
                    [$start, $end] = array_map('intval', explode('-', $part, 2));
                } elseif (ctype_digit($part)) {
                    $start = $end = (int) $part;
                } else {
                    throw new InvalidArgumentException('Invalid cron field: ' . $field);
                }
            }
            $normalized = $max === 7 && $value === 0 && $start === 7 ? 7 : $value;
            if ($start >= $min && $end <= $max && $normalized >= $start && $normalized <= $end && (($normalized - $start) % $step) === 0) {
                return true;
            }
        }
        return false;
    }

    public function run(?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable('now');
        $lockKey = 'schedule:' . hash('sha256', $this->identity . '|' . $this->description);
        if ($this->lockSeconds !== null && Cache::has($lockKey)) {
            return false;
        }

        if ($this->lockSeconds !== null && !Cache::add($lockKey, true, $this->lockSeconds)) {
            return false;
        }

        $runKey = $lockKey . ':run:' . $now->format('YmdHi');
        if (!Cache::add($runKey, true, 120)) {
            if ($this->lockSeconds !== null) {
                Cache::forget($lockKey);
            }
            return false;
        }

        try {
            ($this->callback)();
            return true;
        } finally {
            if ($this->lockSeconds !== null) {
                Cache::forget($lockKey);
            }
        }
    }
}
