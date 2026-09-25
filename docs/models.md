# Models and relations — 0.3 development

SedoPHP models remain lightweight wrappers around the query builder. Models do not require a dependency-injection container, metadata compiler or third-party ORM package.

## hasOne

```php
final class User extends Model
{
    protected string $table = 'users';

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class, 'user_id');
    }
}

$profile = $user->profile()->first();
```

## belongsToMany

```php
public function roles(): BelongsToMany
{
    return $this->belongsToMany(
        Role::class,
        'role_user',
        'user_id',
        'role_id',
    )->withPivot('level');
}
```

```php
$roles = $user->roles()->get();

$pivot = $roles[0]->get('pivot');
```

The pivot payload contains the two relation keys and only explicitly requested extra columns.

Pivot mutations are available directly on the relation:

```php
$user->roles()->attach($roleId, ['level' => 'editor']);
$user->roles()->detach($roleId);

$result = $user->roles()->sync([$adminId, $editorId]);
```

These operations use the normal SedoPHP database layer; no ORM package is involved.

## Eager loading

Existing eager loading continues to work:

```php
$users = User::with('posts');
```

Nested relations:

```php
$users = User::with('posts.user');
```

Constrained eager loading:

```php
$users = User::with([
    'posts' => fn (QueryBuilder $query) => $query->where('published', 1),
]);
```

A constraint may mutate the supplied query or return it.

## Soft deletes

Soft deletes are opt-in per model:

```php
final class Post extends Model
{
    protected string $table = 'posts';
    protected bool $softDeletes = true;
}
```

The table must contain a nullable `deleted_at` column. The schema helper is:

```php
$table->softDeletes();
```

Normal model queries automatically exclude deleted rows:

```php
Post::all();
Post::find($id);
Post::query()->get();
```

Explicit access:

```php
Post::withTrashed()->get();
Post::onlyTrashed()->get();
```

Instance operations:

```php
$post->delete();
$post->trashed();
$post->restore();
$post->forceDelete();
```

Soft deletes remain explicit and opt-in; existing 0.2 models continue to hard-delete unless `$softDeletes` is enabled.
