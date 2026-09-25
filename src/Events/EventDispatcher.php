<?php

declare(strict_types=1);

namespace SedoPHP\Events;

use InvalidArgumentException;

final class EventDispatcher
{
    /** @var array<string,list<callable>> */
    private static array $listeners = [];

    public static function listen(string $event, callable $listener): void
    {
        $event = trim($event);
        if ($event === '') {
            throw new InvalidArgumentException('Event name cannot be empty.');
        }

        self::$listeners[$event][] = $listener;
    }

    /** @return list<mixed> */
    public static function dispatch(object|string $event, mixed $payload = null): array
    {
        $name = is_object($event) ? $event::class : trim($event);
        if ($name === '') {
            throw new InvalidArgumentException('Event name cannot be empty.');
        }

        $value = is_object($event) ? $event : $payload;
        $results = [];

        foreach (self::$listeners[$name] ?? [] as $listener) {
            $results[] = $listener($value);
        }

        return $results;
    }

    public static function forget(string $event): void
    {
        unset(self::$listeners[$event]);
    }

    public static function clear(): void
    {
        self::$listeners = [];
    }
}
