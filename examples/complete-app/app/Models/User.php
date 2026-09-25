<?php

declare(strict_types=1);

namespace Example\CompleteApp\Models;

use SedoPHP\Database\Model;
use SedoPHP\Database\Relations\HasMany;

final class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'password'];
    protected array $hidden = ['password'];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}
