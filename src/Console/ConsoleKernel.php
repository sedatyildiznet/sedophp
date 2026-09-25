<?php

declare(strict_types=1);

namespace SedoPHP\Console;

use RuntimeException;

final class ConsoleKernel
{
    /** @var array<string,Command> */
    private array $commands = [];

    public function discover(string $directory, string $namespace = 'App\\Console\\Commands'): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            require_once $file;

            $class = trim($namespace, '\\') . '\\' . pathinfo($file, PATHINFO_FILENAME);
            if (!class_exists($class)) {
                throw new RuntimeException("Console command class not found: {$class}");
            }

            $command = new $class();
            if (!$command instanceof Command) {
                throw new RuntimeException("Console command must extend " . Command::class . ": {$class}");
            }

            $name = $command->name();
            if (isset($this->commands[$name])) {
                throw new RuntimeException("Duplicate console command: {$name}");
            }

            $this->commands[$name] = $command;
        }
    }

    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /** @param list<string> $arguments */
    public function run(string $name, array $arguments = []): int
    {
        if (!$this->has($name)) {
            throw new RuntimeException("Console command not found: {$name}");
        }

        return $this->commands[$name]->handle($arguments);
    }

    /** @return array<string,string> */
    public function commands(): array
    {
        $result = [];
        foreach ($this->commands as $name => $command) {
            $result[$name] = $command->description();
        }

        ksort($result, SORT_STRING);
        return $result;
    }
}
