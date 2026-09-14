<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';
require __DIR__ . '/TestSuite.php';

use SedoPHP\Tests\Support\TestSuite;

$suite = new TestSuite();
$test = static fn (string $name, callable $callback): void => $suite->test($name, $callback);
$expect = static fn (bool $condition, string $message = 'Expectation failed.'): void => $suite->expect($condition, $message);

return [$suite, $test, $expect];
