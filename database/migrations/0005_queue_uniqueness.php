<?php

declare(strict_types=1);

return [
    'up' => static function (PDO $db): void {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $db->exec('ALTER TABLE jobs ADD COLUMN unique_key VARCHAR(191) NULL');
            $db->exec("ALTER TABLE jobs ADD COLUMN backoff_strategy VARCHAR(20) NOT NULL DEFAULT 'linear'");
            $db->exec('CREATE UNIQUE INDEX jobs_unique_key_index ON jobs (unique_key)');
            return;
        }

        $db->exec('ALTER TABLE jobs ADD COLUMN unique_key TEXT NULL');
        $db->exec("ALTER TABLE jobs ADD COLUMN backoff_strategy TEXT NOT NULL DEFAULT 'linear'");
        $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS jobs_unique_key_index ON jobs (unique_key)');
    },
    'down' => static function (PDO $db): void {
        // SQLite and older shared-hosting builds may not support safe column removal.
        // The forward migration is intentionally additive.
    },
];
