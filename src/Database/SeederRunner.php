<?php

declare(strict_types=1);

namespace SedoPHP\Database;

use RuntimeException;

final class SeederRunner
{
    public function __construct(private readonly string $directory)
    {
    }

    public function run(?string $name = null): int
    {
        if ($name !== null) {
            $this->runFile($this->fileForName($name));
            return 1;
        }

        $files = glob(rtrim($this->directory, '/') . '/*.php') ?: [];
        sort($files, SORT_STRING);

        $count = 0;
        foreach ($files as $file) {
            $this->runFile($file);
            $count++;
        }

        return $count;
    }

    private function runFile(string $file): void
    {
        if (!is_file($file)) {
            throw new RuntimeException("Seeder file not found: {$file}");
        }

        require_once $file;

        $class = 'Database\\Seeders\\' . pathinfo($file, PATHINFO_FILENAME);
        if (!class_exists($class)) {
            throw new RuntimeException("Seeder class not found: {$class}");
        }

        $seeder = new $class();
        if (!$seeder instanceof Seeder) {
            throw new RuntimeException("Seeder must extend " . Seeder::class . ": {$class}");
        }

        $seeder->run();
    }

    private function fileForName(string $name): string
    {
        $name = str_contains($name, '\\')
            ? substr($name, (int) strrpos($name, '\\') + 1)
            : $name;

        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new RuntimeException('Seeder name must be a valid class name.');
        }

        return rtrim($this->directory, '/') . '/' . $name . '.php';
    }
}
