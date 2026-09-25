<?php

declare(strict_types=1);

namespace Example\CompleteApp\Models;

use SedoPHP\Database\Model;
use SedoPHP\Database\Relations\BelongsTo;

final class Post extends Model
{
    protected string $table = 'posts';
    protected array $fillable = ['user_id', 'title', 'body', 'published_at'];
    protected bool $timestamps = true;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
