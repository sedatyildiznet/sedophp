<?php

declare(strict_types=1);

namespace SedoPHP\Database;

use RuntimeException;

abstract class Seeder
{
    abstract public function run(): void;

    /** @param class-string<Seeder> ...$seeders */
    protected function call(string ...$seeders): void
    {
        foreach ($seeders as $seeder) {
            if (!is_subclass_of($seeder, self::class)) {
                throw new RuntimeException("Seeder must extend " . self::class . ": {$seeder}");
            }

            (new $seeder())->run();
        }
    }
}
