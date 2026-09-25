<?php

declare(strict_types=1);

/** @var \SedoPHP\Scheduling\Schedule $schedule */

$schedule->call(static function (): void {
    $expired = db('api_tokens')
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', gmdate('Y-m-d H:i:s'))
        ->select('id')
        ->get();

    foreach ($expired as $token) {
        db('api_tokens')->where('id', $token['id'])->delete();
    }
})
    ->name('tokens.cleanup')
    ->description('Remove expired API tokens')
    ->dailyAt('03:30')
    ->withoutOverlapping();
