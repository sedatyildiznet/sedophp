<?php

declare(strict_types=1);

namespace SedoPHP\Core;

use RuntimeException;
use SedoPHP\Auth\Auth;
use SedoPHP\Cache\Cache;
use SedoPHP\Database\Database;
use SedoPHP\Http\Request;
use SedoPHP\Mail\Mailer;
use SedoPHP\Middleware\ApiTokenMiddleware;
use SedoPHP\Middleware\AuthMiddleware;
use SedoPHP\Middleware\CsrfMiddleware;
use SedoPHP\Middleware\GuestMiddleware;
use SedoPHP\Middleware\JwtMiddleware;
use SedoPHP\Middleware\RateLimitMiddleware;
use SedoPHP\Queue\Queue;
use SedoPHP\Routing\Router;
use SedoPHP\Security\Jwt;
use SedoPHP\Session\Session;
use SedoPHP\View\View;

final class Application
{
    private static ?self $instance = null;
    private Router $router;
    private ?Request $request = null;

    public function __construct(private readonly string $basePath)
    {
        self::$instance = $this;

        Env::load($this->path('.env'));
        Config::load($this->path('config'));

        date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));
        Logger::configure($this->path('storage/logs/app.log'));
        ErrorHandler::register((bool) Config::get('app.debug', false));

        Session::configure((array) Config::get('session', []));
        Session::start();
        Request::configure((array) Config::get('http', []));
        Database::configure((array) Config::get('database', []));
        Queue::configure((array) Config::get('queue', []));
        Auth::configure((array) Config::get('auth', []));
        Jwt::configure((array) Config::get('auth', []));
        Cache::configure((array) Config::get('cache', []), $this->basePath);
        Mailer::configure((array) Config::get('mail', []));
        View::configure($this->path('app/Views'));

        $this->router = new Router();
        $this->router->alias('auth', AuthMiddleware::class);
        $this->router->alias('guest', GuestMiddleware::class);
        $this->router->alias('csrf', CsrfMiddleware::class);
        $this->router->alias('throttle', RateLimitMiddleware::class);
        $this->router->alias('token', ApiTokenMiddleware::class);
        $this->router->alias('jwt', JwtMiddleware::class);

        foreach ((array) Config::get('middleware.aliases', []) as $name => $middleware) {
            if (is_string($name) && is_string($middleware)) {
                $this->router->alias($name, $middleware);
            }
        }

        $routes = $this->path('routes/web.php');
        if (is_file($routes)) {
            require $routes;
        }
    }

    public static function instance(): self
    {
        return self::$instance ?? throw new RuntimeException('SedoPHP application has not been booted.');
    }

    public function run(): void
    {
        $this->request = Request::capture((string) Config::get('app.base_path', ''));
        $this->router->dispatch($this->request)->send();
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function request(): Request
    {
        return $this->request ??= Request::capture((string) Config::get('app.base_path', ''));
    }

    public function path(string $path = ''): string
    {
        return rtrim($this->basePath, '/') . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }
}
