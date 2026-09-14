<?php

declare(strict_types=1);

return [
    'up' => static function (PDO $db): void {
        $id = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $db->exec("CREATE TABLE posts (id {$id}, title VARCHAR(255) NOT NULL, body TEXT NOT NULL, published INTEGER NOT NULL DEFAULT 0, created_at DATETIME NOT NULL)");
    },
    'down' => static fn (PDO $db) => $db->exec('DROP TABLE IF EXISTS posts'),
];
