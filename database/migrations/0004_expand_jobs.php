<?php

declare(strict_types=1);

return [
    'up' => static function (PDO $db): void {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $db->exec("ALTER TABLE jobs ADD COLUMN queue VARCHAR(100) NOT NULL DEFAULT 'default'");
            $db->exec('ALTER TABLE jobs ADD COLUMN backoff INT NOT NULL DEFAULT 30');
            $db->exec('ALTER TABLE jobs ADD COLUMN timeout INT NOT NULL DEFAULT 60');
            $db->exec('CREATE INDEX jobs_queue_index ON jobs (queue, available_at, reserved_at, failed_at)');
            return;
        }
        $db->exec("ALTER TABLE jobs ADD COLUMN queue TEXT NOT NULL DEFAULT 'default'");
        $db->exec('ALTER TABLE jobs ADD COLUMN backoff INTEGER NOT NULL DEFAULT 30');
        $db->exec('ALTER TABLE jobs ADD COLUMN timeout INTEGER NOT NULL DEFAULT 60');
        $db->exec('CREATE INDEX IF NOT EXISTS jobs_queue_index ON jobs (queue, available_at, reserved_at, failed_at)');
    },
    'down' => static function (PDO $db): void {
        // SQLite cannot safely drop these columns on older shared-hosting builds.
    },
];
