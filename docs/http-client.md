# HTTP client — 0.3 development

SedoPHP includes a small outbound HTTP client with no package dependency.

It uses cURL when available and falls back to native PHP streams when cURL is unavailable.

`ext-curl` is not a SedoPHP requirement.

## GET requests

```php
$response = http()
    ->timeout(5)
    ->header('Accept', 'application/json')
    ->get('https://api.example.com/users', [
        'page' => 2,
    ]);

if ($response->successful()) {
    $data = $response->json();
}
```

## Authentication

```php
$response = http()
    ->bearer($token)
    ->get('https://api.example.com/me');
```

## Form POST

```php
$response = http()->post(
    'https://api.example.com/login',
    [
        'email' => 'user@example.test',
        'password' => 'secret',
    ],
);
```

## JSON POST

```php
$response = http()->postJson(
    'https://api.example.com/items',
    ['name' => 'Example'],
);
```

## Response API

```php
$response->status();
$response->body();
$response->headers();
$response->header('content-type');
$response->json();
$response->successful();
```

## Transport selection

Automatic selection is the default:

```php
http()->get($url);
```

A transport can also be selected explicitly:

```php
http()->transport('stream')->get($url);
http()->transport('curl')->get($url);
```

Selecting cURL explicitly throws an exception when the extension is unavailable.

## Safety and scope

The client accepts absolute `http://` and `https://` URLs only. Embedded URL credentials and response redirects are not followed automatically.

Header names and values are validated to prevent header injection.

SedoPHP deliberately does not add a third-party HTTP package to core.
