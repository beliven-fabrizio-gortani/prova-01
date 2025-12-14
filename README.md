# This is my package lockout

[![Latest Version on Packagist](https://img.shields.io/packagist/v/beliven-it/lockout.svg?style=flat-square)](https://packagist.org/packages/beliven-it/lockout)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/beliven-it/lockout/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/beliven-it/lockout/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/beliven-it/lockout/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/beliven-it/lockout/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/beliven-it/lockout.svg?style=flat-square)](https://packagist.org/packages/beliven-it/lockout)

This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/lockout.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/lockout)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

Install the package into your Laravel application:

```bash
composer require beliven-it/lockout
```

Publish the package assets (config, migrations and views). Using the service provider name is recommended to avoid ambiguity:

```bash
php artisan vendor:publish --provider="Beliven\Lockout\LockoutServiceProvider" --tag="config"
php artisan vendor:publish --provider="Beliven\Lockout\LockoutServiceProvider" --tag="migrations"
php artisan vendor:publish --provider="Beliven\Lockout\LockoutServiceProvider" --tag="views"
```

Run the migrations to create the persistent `lockouts` table:

```bash
php artisan migrate
```

Recommended environment variables (add to your `.env` if you want to override defaults):

```dotenv
# Maximum failed attempts before permanent lock (default 5)
LOCKOUT_MAX_ATTEMPTS=5

# If you want automatic expiration instead of permanent locks, set seconds (e.g. 3600 = 1 hour)
LOCKOUT_EXPIRES_AFTER_SECONDS=null

# Use cache for intermediate attempt counters (not required; package stores final state in DB)
LOCKOUT_USE_CACHE=false
```

Example of the published `config/lockout.php` (summary of important options):

```php
return [
    'table' => env('LOCKOUT_TABLE', 'lockouts'),
    'model' => Beliven\Lockout\Models\Lockout::class,
    'max_attempts' => env('LOCKOUT_MAX_ATTEMPTS', 5),
    'expires_after_seconds' => env('LOCKOUT_EXPIRES_AFTER_SECONDS', null),
    'use_cache' => env('LOCKOUT_USE_CACHE', false),
    'middleware' => [
        'alias' => 'lockout.check',
    ],
    'message' => 'Your account has been locked due to too many failed login attempts. Please contact support to restore access.',
];
```

Notes:
- The package stores lockout state in the database (persistent) — by default locks do not expire unless you set `LOCKOUT_EXPIRES_AFTER_SECONDS`.
- The package registers a middleware alias (default `lockout.check`). You may apply it to your login route(s) to prevent locked accounts from attempting authentication.

## Usage

The package exposes a container-bound service `\Beliven\Lockout\Lockout` and a facade alias if you prefer. Typical usage is to record a failed attempt when an authentication attempt fails, and to protect login endpoints via middleware.

Record a failed attempt (e.g. in your authentication failure logic or listener):

```php
// Using the container-resolved service
app(\Beliven\Lockout\Lockout::class)->recordFailedAttempt($emailOrIdentifier, $user?->id ?? null, [
    'ip' => request()->ip(),
    'user_agent' => request()->userAgent(),
]);

// Or using the facade (if you have the alias set up)
\Lockout::recordFailedAttempt($emailOrIdentifier, $user?->id ?? null, ['ip' => request()->ip()]);
```

Check whether an identifier is locked:

```php
if (app(\Beliven\Lockout\Lockout::class)->isLocked($emailOrIdentifier)) {
    // Deny login or show a locked message
}
```

Manually lock or unlock an identifier:

```php
// Lock
app(\Beliven\Lockout\Lockout::class)->lock($emailOrIdentifier, $userId ?? null, 'admin_locked', ['note' => 'manual lock']);

// Unlock (optionally reset attempts)
app(\Beliven\Lockout\Lockout::class)->unlock($emailOrIdentifier, true);
```

Middleware
- The package registers a middleware alias (by default `lockout.check`). Apply it to your login route to automatically block locked accounts before authentication runs:

```php
Route::post('login', [LoginController::class, 'login'])->middleware('lockout.check');
```

Events
- The package dispatches events you can listen to:
  - `Beliven\Lockout\Events\FailedAttempt`
  - `Beliven\Lockout\Events\UserLocked`
  - `Beliven\Lockout\Events\UserUnlocked`

Testing
- Use your usual test tools (Pest / PHPUnit). Example testbench-based tests should resolve the service and assert DB state in the `lockouts` table.

If you want, I can:
- Add example listeners that send an email when a user is locked,
- Add example unit/integration tests (Pest + Orchestra Testbench),
- Or update the README with troubleshooting and upgrade notes.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Fabrizio Gortani](https://github.com/beliven-fabrizio-gortani)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
