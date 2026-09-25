# Console — 0.3 development

SedoPHP's CLI remains optional. HTTP requests never depend on console bootstrapping and every generated file is ordinary PHP that can be created manually.

## Generators

Existing generators remain available:

```bash
php sedo make:controller UserController
php sedo make:model User
php sedo make:migration create_posts
```

0.3 adds:

```bash
php sedo make:middleware AdminMiddleware
php sedo make:request StoreUserRequest
php sedo make:job SendWelcomeMail
php sedo make:seeder UserSeeder
php sedo make:factory UserFactory
php sedo make:command CleanupCommand
```

Generators create directories when needed and do not require Composer.

## Application commands

`make:command` creates a class under `app/Console/Commands`:

```php
namespace App\Console\Commands;

use SedoPHP\Console\Command;

final class CleanupCommand extends Command
{
    protected string $name = 'app:cleanup';
    protected string $description = 'Remove expired application data';

    public function handle(array $arguments): int
    {
        // application work
        return 0;
    }
}
```

SedoPHP discovers classes in this directory by convention when an unknown built-in command is requested.

Run the command:

```bash
php sedo app:cleanup
```

Arguments after the command name are passed as a plain list to `handle()`.

No service provider or command container is required.

## Cache

Clear the configured application cache:

```bash
php sedo cache:clear
```

The dependency-free file cache remains the default.

## Configuration inspection

Show all loaded configuration:

```bash
php sedo config:show
```

Show one value or subtree:

```bash
php sedo config:show database
php sedo config:show app.env
```

Common password, secret, token and key fields are redacted in CLI output.

## Routes

The existing command remains:

```bash
php sedo route:list
```

`php sedo routes` is an alias.

0.3 route output also shows route names.

## Seeders

```bash
php sedo db:seed
php sedo db:seed UserSeeder
```

See [testing.md](testing.md) for seeders and factories.
