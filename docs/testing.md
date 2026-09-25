# Testing and test data — 0.3 development

SedoPHP 0.3 keeps application testing dependency-free. PHPUnit and Faker can still be used by applications that want them, but neither is required by the framework.

## Seeders

Generate a seeder:

```bash
php sedo make:seeder UserSeeder
```

Generated seeders live under `database/seeders/` and are plain PHP classes:

```php
namespace Database\Seeders;

use SedoPHP\Database\Seeder;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        db('users')->insert([
            'name' => 'Example',
            'email' => 'example@example.test',
        ]);
    }
}
```

Run all seeders:

```bash
php sedo db:seed
```

Run one:

```bash
php sedo db:seed UserSeeder
```

Seeders are available with both Composer and SedoPHP's fallback autoloader.

## Model factories

Factories use native PHP randomness and do not depend on Faker.

Generate a factory:

```bash
php sedo make:factory UserFactory
```

Example:

```php
namespace Database\Factories;

use App\Models\User;
use SedoPHP\Database\ModelFactory;

final class UserFactory extends ModelFactory
{
    protected string $model = User::class;

    protected function definition(): array
    {
        return [
            'name' => 'User ' . $this->randomString(8),
            'email' => $this->email(),
        ];
    }
}
```

Usage:

```php
$user = UserFactory::new()->create();

$users = UserFactory::new()
    ->count(10)
    ->create();

$admin = UserFactory::new()
    ->state(['role' => 'admin'])
    ->create();
```

Available native helpers include random strings, email addresses, integers, UUID v4 values and timestamps.

`make()` creates model objects without writing them to the database. `create()` persists them through the normal model API.

## HTTP test client

The HTTP test client dispatches requests directly through SedoPHP's router. No web server is required.

```php
use SedoPHP\Testing\TestClient;

$client = TestClient::fromApplication(app());

$client->get('/users')
    ->assertStatus(200)
    ->assertSee('Users');
```

JSON requests:

```php
$client->postJson('/api/login', [
    'email' => 'user@example.test',
    'password' => 'secret',
])
    ->assertStatus(200)
    ->assertJson(['ok' => true])
    ->assertJsonPath('user.email', 'user@example.test');
```

Response assertions currently include:

- `assertStatus()`;
- `assertHeader()`;
- `assertSee()`;
- `assertJson()`;
- `assertJsonPath()`;
- `assertRedirect()`.

## Database assertions

```php
use SedoPHP\Testing\DatabaseAssertions;

DatabaseAssertions::assertHas('users', [
    'email' => 'user@example.test',
]);

DatabaseAssertions::assertMissing('users', [
    'email' => 'deleted@example.test',
]);
```

Assertions throw a normal `RuntimeException` when they fail, so they can be used with SedoPHP's lightweight test runner or another testing framework.

## Production impact

Testing helpers have no third-party dependencies and are not part of the HTTP request lifecycle unless application code explicitly uses them.
