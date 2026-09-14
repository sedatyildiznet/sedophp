<?php

declare(strict_types=1);

namespace SedoPHP\Routing;

final class Route
{
    /** @var list<string> */
    private array $middleware = [];

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

    /** @return list<string> */
    public function middlewareNames(): array
    {
        return $this->middleware;
    }
}
