# Authentication — 0.3 development

SedoPHP keeps authentication primitives small and application-controlled. The framework does not generate a UI, require a frontend stack or add an external identity service.

## Login throttling

Session login attempts use the existing file-backed rate limiter by default.

Environment options:

```dotenv
AUTH_LOGIN_MAX_ATTEMPTS=5
AUTH_LOGIN_DECAY_SECONDS=60
```

Set `AUTH_LOGIN_MAX_ATTEMPTS=0` to disable built-in login throttling for an application that provides its own limiter.

Successful authentication clears the counter. Applications can explicitly clear an identity's counter:

```php
SedoPHP\Auth\Auth::clearLoginAttempts('user@example.com');
```

The existing helpers remain unchanged:

```php
login($email, $password);
logout();
auth();
user();
```

## Password reset tokens

Password-reset tokens use native cryptographic randomness and the configured cache. Only a SHA-256-derived cache key is retained; the plaintext token is returned only when it is issued.

```php
use SedoPHP\Auth\PasswordReset;

$token = PasswordReset::issue($userId);

// deliver $token through the application's mail flow

if (PasswordReset::reset($token, $newPassword)) {
    // password changed
}
```

Tokens expire and are consumed atomically on first use through the file-cache implementation.

Default TTL:

```dotenv
AUTH_PASSWORD_RESET_TTL=3600
```

A token can be revoked before use:

```php
PasswordReset::revoke($token);
```

Password policy remains application-owned. Validate password length and complexity before calling the reset primitive.

## Email verification tokens

```php
use SedoPHP\Auth\EmailVerification;

$token = EmailVerification::issue($userId, [
    'email' => $userEmail,
]);

$payload = EmailVerification::verify($token);

if ($payload !== null) {
    $userId = $payload['subject'];
    $email = $payload['metadata']['email'] ?? null;

    // mark the account as verified in application code
}
```

Verification tokens are also single-use and expiring.

Default TTL:

```dotenv
AUTH_EMAIL_VERIFICATION_TTL=86400
```

SedoPHP deliberately does not decide which database column represents email verification.

## Named routes

Routes can be named without changing the existing routing style:

```php
get('/users/{id}', 'UserController@show')
    ->name('users.show');
```

Generate a URL:

```php
route('users.show', ['id' => 5]);
```

Pass `false` as the third argument to generate a path instead of an absolute URL:

```php
route('users.show', ['id' => 5], false);
```

Extra parameters are encoded into the query string.

## Signed URLs

Set an application signing key:

```dotenv
APP_KEY=use-a-long-random-secret
```

Generate a permanent signed route:

```php
signed_route('users.show', ['id' => 5]);
```

Or an expiring route:

```php
signed_route(
    'users.show',
    ['id' => 5],
    '+30 minutes',
);
```

Protect the destination:

```php
get('/users/{id}', 'UserController@show')
    ->name('users.show')
    ->middleware('signed');
```

Signatures use native `hash_hmac('sha256', ...)` and compare with `hash_equals()`.

## Optional FormRequest validation

The existing `validate()` helper remains the simplest validation API.

For larger controllers, validation can be moved into an optional request class:

```php
use SedoPHP\Http\FormRequest;

final class StoreUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required|email',
        ];
    }
}

$request = new StoreUserRequest(request());

if ($request->fails()) {
    $errors = $request->errors();
}

$data = $request->validated();
```

`validated()` throws HTTP 422 when validation fails. This layer is optional and does not replace the existing validator.
