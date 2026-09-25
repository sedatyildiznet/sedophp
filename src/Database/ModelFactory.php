<?php

declare(strict_types=1);

namespace SedoPHP\Database;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/** @template T of Model */
abstract class ModelFactory
{
    /** @var class-string<T> */
    protected string $model;

    private int $times = 1;

    /** @var list<array<string,mixed>|callable(array<string,mixed>,int):array<string,mixed>> */
    private array $states = [];

    abstract protected function definition(): array;

    public static function new(): static
    {
        return new static();
    }

    public function count(int $count): static
    {
        if ($count < 1) {
            throw new InvalidArgumentException('Factory count must be at least 1.');
        }

        $clone = clone $this;
        $clone->times = $count;
        return $clone;
    }

    /** @param array<string,mixed>|callable(array<string,mixed>,int):array<string,mixed> $state */
    public function state(array|callable $state): static
    {
        $clone = clone $this;
        $clone->states[] = $state;
        return $clone;
    }

    /**
     * @param array<string,mixed> $overrides
     * @return T|list<T>
     */
    public function make(array $overrides = []): Model|array
    {
        return $this->build($overrides, false);
    }

    /**
     * @param array<string,mixed> $overrides
     * @return T|list<T>
     */
    public function create(array $overrides = []): Model|array
    {
        return $this->build($overrides, true);
    }

    /**
     * @param array<string,mixed> $overrides
     * @return T|list<T>
     */
    private function build(array $overrides, bool $persist): Model|array
    {
        if (!isset($this->model) || !is_subclass_of($this->model, Model::class)) {
            throw new RuntimeException(static::class . ' must define a valid $model class.');
        }

        $models = [];
        $class = $this->model;

        for ($index = 0; $index < $this->times; $index++) {
            $data = $this->definition();

            foreach ($this->states as $state) {
                $data = is_callable($state)
                    ? array_merge($data, $state($data, $index))
                    : array_merge($data, $state);
            }

            $data = array_merge($data, $overrides);
            $models[] = $persist ? $class::create($data) : new $class($data);
        }

        return $this->times === 1 ? $models[0] : $models;
    }

    protected function randomString(int $length = 16): string
    {
        if ($length < 1) {
            throw new InvalidArgumentException('Random string length must be at least 1.');
        }

        return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
    }

    protected function email(string $domain = 'example.test'): string
    {
        $domain = trim($domain);
        if ($domain === '' || preg_match('/^[A-Za-z0-9.-]+$/', $domain) !== 1) {
            throw new InvalidArgumentException('Factory email domain is invalid.');
        }

        return 'user_' . $this->randomString(12) . '@' . strtolower($domain);
    }

    protected function integer(int $min = 0, int $max = 1000): int
    {
        if ($min > $max) {
            throw new InvalidArgumentException('Factory integer minimum cannot exceed maximum.');
        }

        return random_int($min, $max);
    }

    protected function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    protected function timestamp(?DateTimeImmutable $at = null): string
    {
        return ($at ?? new DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
