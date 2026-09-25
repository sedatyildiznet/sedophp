<?php

declare(strict_types=1);

namespace SedoPHP\Tests\Support;

use RuntimeException;
use Throwable;

final class TestSuite
{
    private int $passed = 0;
    private int $failed = 0;

    public function test(string $name, callable $callback): void
    {
        try {
            $callback();
            $this->passed++;
            echo "[PASS] {$name}\n";
        } catch (Throwable $exception) {
            $this->failed++;
            echo "[FAIL] {$name}: {$exception->getMessage()}\n";
        }
    }

    public function expect(bool $condition, string $message = 'Expectation failed.'): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    public function finish(string $label = ''): int
    {
        $prefix = $label === '' ? '' : $label . ': ';
        echo "\n{$prefix}{$this->passed} passed, {$this->failed} failed.\n";
        return $this->failed === 0 ? 0 : 1;
    }
}
