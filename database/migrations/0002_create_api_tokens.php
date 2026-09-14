<?php

declare(strict_types=1);

return [
    'up' => static function (PDO $db): void {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $db->exec(
                'CREATE TABLE IF NOT EXISTS api_tokens (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT NOT NULL,
                    name VARCHAR(100) NOT NULL,
                    token_hash CHAR(64) NOT NULL UNIQUE,
                    abilities TEXT NOT NULL,
                    expires_at DATETIME NULL,
                    last_used_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    INDEX api_tokens_user_id_index (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
            return;
        }

        $db->exec(
            'CREATE TABLE IF NOT EXISTS api_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                abilities TEXT NOT NULL,
                expires_at TEXT NULL,
                last_used_at TEXT NULL,
                created_at TEXT NOT NULL
            )'
        );
        $db->exec('CREATE INDEX IF NOT EXISTS api_tokens_user_id_index ON api_tokens (user_id)');
    },
    'down' => static function (PDO $db): void {
        $db->exec('DROP TABLE IF EXISTS api_tokens');
    },
];
