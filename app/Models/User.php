<?php

declare(strict_types=1);

namespace App\Models;

use SedoPHP\Database\Model;

final class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'password'];
    protected array $hidden = ['password'];
}
