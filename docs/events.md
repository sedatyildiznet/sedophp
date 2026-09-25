# Events — 0.3 development

SedoPHP includes a minimal synchronous event dispatcher. It is intentionally small and does not require a container, queue worker or external broker.

## Object events

```php
final class UserRegistered
{
    public function __construct(public readonly int $userId) {}
}

listen(UserRegistered::class, function (UserRegistered $event): void {
    log_info('User registered', ['user_id' => $event->userId]);
});

event(new UserRegistered(42));
```

Listeners run immediately in registration order.

## Named events

```php
listen('order.paid', function (array $payload): void {
    // ...
});

event('order.paid', [
    'order_id' => 100,
]);
```

`event()` returns listener return values as a list.

## Removing listeners

```php
SedoPHP\Events\EventDispatcher::forget('order.paid');
SedoPHP\Events\EventDispatcher::clear();
```

Events are synchronous by default. Applications that need asynchronous work can dispatch a normal SedoPHP queue job from a listener.
