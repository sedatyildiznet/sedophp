# SedoPHP advanced features — 0.2

SedoPHP 0.2 expands the framework without changing its shared-hosting-first design.

## Route groups and global middleware

Routes can be grouped with a shared prefix and middleware. Groups can be nested:

~~~php
route_group(['prefix' => '/api', 'middleware' => 'token'], function () {
    route_group(['prefix' => '/v1'], function () {
        get('/me', 'ApiController@me');
    });
});
~~~

Global middleware is configured in `config/middleware.php`. CORS and security headers are enabled globally by default so they also cover 404, 405 and OPTIONS responses.

## Pagination

~~~php
$page = db('posts')
    ->where('published', 1)
    ->orderBy('id', 'desc')
    ->paginate(20, (int) request()->query('page', 1));
~~~

The result contains data, current_page, per_page, total, last_page, from and to.

## Query joins, grouping and HAVING

~~~php
$rows = db('users')
    ->select('users.name', 'posts.title')
    ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
    ->where('users.active', 1)
    ->get();

$groups = db('users')
    ->select('users.active')
    ->groupBy('users.active')
    ->having('users.active', 1)
    ->paginate(20);
~~~

Grouped pagination calculates totals using a wrapped count query.

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

## Model casts and timestamps

Casts and timestamps are opt-in:

~~~php
final class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'active', 'settings'];

    protected array $casts = [
        'active' => 'boolean',
        'settings' => 'array',
    ];

    protected bool $timestamps = true;
}
~~~

Supported casts include integer, float, boolean, string, array/json and datetime. When timestamps are enabled, `created_at` and `updated_at` are managed automatically.

## Nested validation

Dot paths and wildcard arrays can be validated directly:

~~~php
$errors = validate($data, [
    'profile.email' => 'required|email',
    'items.*.name' => 'required|string',
    'items.*.password' => 'required|confirmed',
    'items.*.mirror' => 'same:items.*.name',
]);
~~~

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

$tokens = jwt_pair(['sub' => 5]);
$replacement = jwt_refresh($tokens['refresh_token']);
jwt_revoke($replacement['access_token']);

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

CC, BCC and attachments can be supplied through the options argument:

~~~php
mail_send('user@example.com', 'Report', '<b>Ready</b>', 'Ready', [], [
    'cc' => ['team@example.com'],
    'bcc' => ['audit@example.com'],
    'attachments' => [
        ['path' => app()->path('storage/reports/monthly.pdf'), 'name' => 'report.pdf'],
    ],
]);
~~~

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

queue_push(
    SendWelcomeMail::class,
    ['user_id' => 5],
    delaySeconds: 0,
    maxAttempts: 5,
    queue: 'mail',
    backoffSeconds: 60,
    timeoutSeconds: 120,
);
~~~

Process queued work:

~~~bash
php sedo queue:work 20 mail
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

Cron expressions and per-task timezones are supported:

~~~php
$schedule->call($callback)
    ->cron('*/15 9-18 * * 1-5')
    ->timezone('Europe/Istanbul');
~~~

On shared hosting, configure cPanel Cron to execute once per minute:

~~~bash
php /home/USER/public_html/sedo schedule:run
~~~

No permanent scheduler daemon is required.
Each task is executed at most once per due minute, and overlap-skipped tasks are not counted as completed.

## Schema builder

New migrations can use the portable schema builder:

~~~php
use SedoPHP\Database\Blueprint;
use SedoPHP\Database\Schema;

Schema::create('posts', static function (Blueprint $table): void {
    $table->id();
    $table->foreignId('user_id');
    $table->string('title');
    $table->boolean('published')->defaultValue(false);
    $table->timestamps();
    $table->index('user_id');
});
~~~

`Schema::table()`, `drop()`, `dropIfExists()`, `rename()`, `hasTable()` and `hasColumn()` are also available. Existing PDO-based migrations remain valid.

## CORS and security headers

Configure allowed browser origins as a comma-separated list:

~~~dotenv
CORS_ORIGINS=https://app.example.com,https://admin.example.com
CORS_ALLOW_CREDENTIALS=false
CORS_MAX_AGE=600
CORS_EXPOSE_HEADERS=X-Request-Id
CONTENT_SECURITY_POLICY=
~~~

SedoPHP adds `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` and `Permissions-Policy` by default. An optional Content Security Policy can be supplied through the environment. CORS response headers are emitted only for explicitly allowed origins.
