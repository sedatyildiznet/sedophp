<?php

declare(strict_types=1);

return [
    'retry_after' => (int) env('QUEUE_RETRY_AFTER', 300),
];
