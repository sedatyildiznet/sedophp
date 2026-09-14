# SedoPHP advanced features — 0.2 development

The 0.2 development line expands SedoPHP without changing its shared-hosting-first design.

## Pagination

~~~php
$page = db('posts')
    ->where('published', 1)
    ->orderBy('id', 'desc')
    ->paginate(20, (int) request()->query('page', 1));
~~~

The result contains data, current_page, per_page, total, last_page, from and to.

## Model relations

~~~php
final class User extends Model
{
    protected string $table = 'users';

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}

final class Post extends Model
{
    protected string $table = 'posts';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

$posts = $user->posts()->get();
$owner = $post->user()->first();

// Fetch all users and their posts in two queries.
$users = User::with('posts');
~~~

Both relation objects expose query() for additional query-builder constraints. `with()` accepts one relation name or a list of relation names and includes hydrated relations in model arrays/JSON.

## Cache

The built-in file cache stores data under storage/cache by default and supports TTL values, remember(), forget(), clear() and atomic integer increments.

~~~php
cache_put('settings', ['theme' => 'dark'], 300);
$settings = cache_get('settings');

$total = cache_remember('order-count', 60, function () {
    return db('orders')->count();
});

cache_forget('settings');
~~~

## Rate limiting

~~~php
get('/api/search', 'SearchController@index')
    ->middleware('throttle:60,60');
~~~

The first parameter is the allowed attempt count and the second is the decay window in seconds. Responses include rate-limit headers and return HTTP 429 after the limit is exceeded.

Forwarded client IP headers are ignored unless the direct peer is explicitly trusted:

~~~dotenv
TRUSTED_PROXIES=127.0.0.1,10.0.0.0/8
~~~

Use only proxy IPs or CIDR ranges you control. When configured, Cloudflare's `CF-Connecting-IP` and standard `X-Forwarded-For` are supported without allowing clients to spoof their address directly.

## Database API tokens

Run migrations first:

~~~bash
php sedo migrate
~~~

Issue a token:

~~~php
$token = api_token_issue(
    userId: 5,
    name: 'mobile',
    abilities: ['posts:read', 'posts:write'],
);
~~~

Protect an endpoint:

~~~php
get('/api/me', function () {
    return json([
        'user_id' => request()->attribute('token_user_id'),
        'can_write' => token_can('posts:write'),
    ]);
})->middleware('token');
~~~

Only a SHA-256 hash of the generated token is stored in the database.
Tokens whose user no longer exists are rejected and removed automatically.

## JWT

Configure a strong secret of at least 32 characters:

~~~dotenv
JWT_SECRET=replace-with-a-long-random-secret
JWT_ISSUER=https://example.com
~~~

~~~php
$jwt = jwt_encode(['sub' => 5], 3600);
$claims = jwt_decode($jwt);

get('/api/private', function () {
    return json(['user_id' => jwt_claim('sub')]);
})->middleware('jwt');
~~~

JWT uses HS256 and requires a valid `exp` claim. When `JWT_ISSUER` is configured, a matching `iss` claim is also required.

## Mail

Available mail drivers are log, native PHP mail and SMTP.

~~~dotenv
MAIL_DRIVER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=user
MAIL_PASSWORD=secret
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME=SedoPHP
~~~

~~~php
mail_send(
    'user@example.com',
    'Welcome',
    '<h1>Welcome</h1>',
    'Welcome',
);
~~~

The SMTP implementation supports TLS/SSL and AUTH LOGIN without a third-party runtime package.

## Database queue

Jobs implement SedoPHP\Queue\JobInterface:

~~~php
final class SendWelcomeMail implements JobInterface
{
    public function handle(array $payload): void
    {
        // send mail
    }
}

queue_push(SendWelcomeMail::class, ['user_id' => 5]);
~~~

Process queued work:

~~~bash
php sedo queue:work 20
~~~

Jobs track attempts, retry with a short backoff and retain failed rows for inspection.
Stale reservations left by a crashed worker are released automatically. The timeout must be longer than the longest expected job:

~~~dotenv
QUEUE_RETRY_AFTER=300
~~~

Failed-job operations:

~~~bash
php sedo queue:failed
php sedo queue:retry 15
php sedo queue:retry all
php sedo queue:flush
~~~

## Scheduler

Define scheduled work in routes/schedule.php:

~~~php
$schedule->call(static function (): void {
    log_info('Daily cleanup');
})
    ->dailyAt('03:00')
    ->withoutOverlapping();
~~~

Available scheduling helpers include everyMinute(), hourly(), daily(), dailyAt(), weeklyOn(), weekdays() and when().

On shared hosting, configure cPanel Cron to execute once per minute:

~~~bash
php /home/USER/public_html/sedo schedule:run
~~~

No permanent scheduler daemon is required.
Each task is executed at most once per due minute, and overlap-skipped tasks are not counted as completed.
