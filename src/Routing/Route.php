<?php

declare(strict_types=1);

namespace SedoPHP\Routing;

use InvalidArgumentException;

final class Route
{
    /** @var list<string> */
    private array $middleware = [];

    private ?string $routeName = null;

    public function __construct(
        public readonly string $method,
        public readonly string $pattern,
        public readonly mixed $action,
    ) {
    }

    public function middleware(string ...$names): self
    {
        array_push($this->middleware, ...$names);
        return $this;
    }

    public function name(string $name): self
    {
        if (preg_match('/^[A-Za-z0-9_.-]+$/', $name) !== 1) {
            throw new InvalidArgumentException("Invalid route name: {$name}");
        }

        $this->routeName = $name;
        return $this;
    }

    public function routeName(): ?string
    {
        return $this->routeName;
    }

    /** @return list<string> */
    public function middlewareNames(): array
    {
        return $this->middleware;
    }
}
