<?php

declare(strict_types=1);

return [
    'up' => static function (PDO $db): void {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $db->exec(
                'CREATE TABLE IF NOT EXISTS jobs (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    job VARCHAR(255) NOT NULL,
                    payload LONGTEXT NOT NULL,
                    attempts INT NOT NULL DEFAULT 0,
                    max_attempts INT NOT NULL DEFAULT 3,
                    available_at DATETIME NOT NULL,
                    reserved_at DATETIME NULL,
                    failed_at DATETIME NULL,
                    last_error TEXT NULL,
                    created_at DATETIME NOT NULL,
                    INDEX jobs_available_index (available_at, reserved_at, failed_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            return;
        }

        $db->exec(
            'CREATE TABLE IF NOT EXISTS jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                job TEXT NOT NULL,
                payload TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL DEFAULT 3,
                available_at TEXT NOT NULL,
                reserved_at TEXT NULL,
                failed_at TEXT NULL,
                last_error TEXT NULL,
                created_at TEXT NOT NULL
            )'
        );
        $db->exec(
            'CREATE INDEX IF NOT EXISTS jobs_available_index
             ON jobs (available_at, reserved_at, failed_at)'
        );
    },
    'down' => static function (PDO $db): void {
        $db->exec('DROP TABLE IF EXISTS jobs');
    },
];
