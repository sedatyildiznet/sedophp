# Database — 0.3 development

SedoPHP 0.3 keeps PDO as the database foundation and adds convenience APIs without hiding the underlying connection.

Supported database targets remain:

- MySQL/MariaDB;
- SQLite.

No database package is required beyond PDO and the appropriate PDO driver.

## Query builder additions

### Upsert

```php
db('settings')->upsert([
    ['setting_key' => 'theme', 'setting_value' => 'dark'],
    ['setting_key' => 'locale', 'setting_value' => 'tr'],
], 'setting_key', ['setting_value']);
```

The database must have a matching unique or primary-key constraint for conflict detection.

### Update or insert

```php
db('settings')->updateOrInsert(
    ['setting_key' => 'theme'],
    ['setting_value' => 'light'],
);
```

### First or create / first or new

```php
$row = db('settings')->firstOrCreate(
    ['setting_key' => 'locale'],
    ['setting_value' => 'tr'],
);

$unsaved = db('settings')->firstOrNew(
    ['setting_key' => 'timezone'],
    ['setting_value' => 'Europe/Istanbul'],
);
```

`firstOrNew()` does not write to the database.

## Chunking and cursors

Large result sets can be processed in bounded batches:

```php
db('users')
    ->orderBy('id')
    ->chunk(250, function (array $rows, int $page) {
        // process one batch
    });
```

Or iterated lazily:

```php
foreach (db('users')->orderBy('id')->cursor(250) as $user) {
    // process one row
}
```

Both APIs use ordinary SELECT queries and require no worker or extension.

## EXISTS and column comparisons

```php
$posts = db('posts')
    ->select('id')
    ->whereColumn('posts.user_id', '=', 'users.id')
    ->where('published', 1);

$users = db('users')
    ->whereExists($posts)
    ->get();
```

`whereNotExists()` is also available. Subqueries remain QueryBuilder instances so identifiers continue through the same validation and quoting rules.

## Transactions

Existing callback transactions remain valid:

```php
transaction(function () {
    // writes
});
```

Nested transactions use savepoints on supported SedoPHP database targets:

```php
transaction(function () {
    transaction(function () {
        // nested unit of work
    });
});
```

Retry attempts can be requested for retryable database transaction errors:

```php
transaction(function () {
    // writes
}, attempts: 3);
```

Retries are opt-in. SedoPHP does not silently repeat arbitrary transactions by default.

## Schema additions

Soft-delete column:

```php
$table->softDeletes();
```

Rename a column:

```php
Schema::table('posts', function (Blueprint $table) {
    $table->renameColumn('title', 'headline');
});
```

Foreign keys:

```php
$table->foreignId('user_id');
$table->foreign(
    'user_id',
    'users',
    'id',
    onDelete: 'CASCADE',
);
```

Composite indexes continue to use arrays:

```php
$table->unique(['user_id', 'slug']);
```

Indexes can be removed by explicit name with `dropIndex()`. Foreign constraints can be removed with `dropForeign()` on MySQL/MariaDB.

SQLite cannot add or drop a foreign-key constraint in place through `Schema::table()`. SedoPHP throws an explicit exception instead of pretending the operation succeeded. Define SQLite foreign keys during `Schema::create()` or rebuild the table in the migration.

SQLite foreign-key enforcement is enabled by default for SedoPHP connections. It can be disabled explicitly with the database configuration key `foreign_keys => false` when required for a controlled migration scenario.
