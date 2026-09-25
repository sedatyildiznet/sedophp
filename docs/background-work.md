# Cache, queue and scheduler — 0.3 development

SedoPHP 0.3 keeps background-work infrastructure replaceable without making external services mandatory.

The default deployment still uses:

- file-backed cache;
- database-backed queue;
- normal cron-driven scheduling.

Redis, RabbitMQ, Supervisor and permanent worker daemons are optional application choices, not SedoPHP runtime requirements.

## Cache drivers

The public `Cache` API remains unchanged:

```php
Cache::put('key', 'value', 60);
Cache::get('key');
Cache::remember('key', 60, fn () => 'value');
Cache::increment('counter');
```

The default implementation is `FileCacheDriver`.

Custom drivers implement:

```php
SedoPHP\Cache\CacheDriverInterface
```

and can be installed at runtime:

```php
Cache::useDriver($driver);
```

This allows applications to add their own Redis or other cache adapters without adding those dependencies to SedoPHP core.

## Queue drivers

The default queue remains the database queue and continues to work with one-shot cron execution:

```bash
php sedo queue:work 20 default
```

Storage behavior is defined by:

```php
SedoPHP\Queue\QueueDriverInterface
```

The built-in implementation is `DatabaseQueueDriver`.

A custom driver can be installed with:

```php
Queue::useDriver($driver);
```

No driver registration container is required.

## Unique jobs

A job can be prevented from being queued twice while an existing row with the same key remains pending or failed:

```php
$id = Queue::pushUnique(
    'user:42:welcome-mail',
    SendWelcomeMail::class,
    ['user_id' => 42],
);
```

The helper form is:

```php
queue_push_unique(
    'user:42:welcome-mail',
    SendWelcomeMail::class,
    ['user_id' => 42],
);
```

The database driver enforces uniqueness with an indexed nullable `unique_key` column. Normal jobs keep this field null.

After a successful job is deleted from the queue, the same unique key can be queued again.

## Backoff strategies

Existing linear backoff remains the default for compatibility.

```php
Queue::push(
    ProcessImport::class,
    ['import_id' => 10],
    backoffSeconds: 30,
    backoffStrategy: 'linear',
);
```

Exponential backoff is opt-in:

```php
Queue::push(
    ProcessImport::class,
    ['import_id' => 10],
    maxAttempts: 5,
    backoffSeconds: 10,
    backoffStrategy: 'exponential',
);
```

The delay grows from the configured base and is capped at one hour.

## Named scheduled tasks

Scheduled tasks can have stable names:

```php
$schedule->call(function (): void {
    // cleanup
})
    ->name('daily.cleanup')
    ->dailyAt('03:00');
```

Names are used for task identity and diagnostic output.

## Scheduler lifecycle hooks

```php
$schedule->call($callback)
    ->name('reports.generate')
    ->before(fn () => log_info('Report starting'))
    ->onSuccess(fn () => log_info('Report completed'))
    ->onFailure(fn (Throwable $e) => log_error('Report failed', [
        'error' => $e->getMessage(),
    ]))
    ->after(fn () => log_info('Report finished'));
```

A failure hook does not swallow the original exception. The exception is rethrown after the failure hook runs.

The `after` hook runs for successful and failed executions.

## Scheduler execution logs

Executed tasks write structured diagnostic context through SedoPHP's logger:

- task name or description;
- result;
- execution duration in milliseconds.

Skipped tasks caused by overlap or duplicate-minute protection are not reported as executed tasks.

## Shared-hosting behavior

Nothing in this milestone changes the minimum deployment path.

A cPanel-style installation can continue to use cron entries such as:

```text
* * * * * php /home/account/app/sedo schedule:run
* * * * * php /home/account/app/sedo queue:work 20 default
```

No permanent process is required.
