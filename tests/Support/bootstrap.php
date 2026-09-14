<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';
require __DIR__ . '/TestSuite.php';

use SedoPHP\Tests\Support\TestSuite;

$suite = new TestSuite();

$test = static function (string $name, callable $callback) use ($suite): void {
    $suite->test($name, $callback);
};

$expect = static function (bool $condition, string $message = 'Expectation failed.') use ($suite): void {
    $suite->expect($condition, $message);
};

return [$suite, $test, $expect];
