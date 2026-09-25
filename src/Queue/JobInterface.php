<?php

declare(strict_types=1);

namespace SedoPHP\Queue;

interface JobInterface
{
    /** @param array<string, mixed> $payload */
    public function handle(array $payload): void;
}
