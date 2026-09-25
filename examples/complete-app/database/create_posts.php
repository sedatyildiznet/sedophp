<?php

declare(strict_types=1);

use SedoPHP\Database\Blueprint;
use SedoPHP\Database\Schema;

return [
    'up' => static function (): void {
        Schema::create('posts', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('title', 160);
            $table->text('body');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'published_at']);
            $table->foreign('user_id', 'users', 'id', onDelete: 'CASCADE');
        });
    },
    'down' => static function (): void {
        Schema::dropIfExists('posts');
    },
];
