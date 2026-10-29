# Laravel API Key Alert

[![Latest version](https://img.shields.io/packagist/v/darvis/laravel-api-key-alert.svg)](https://packagist.org/packages/darvis/laravel-api-key-alert)
[![Tests](https://github.com/ArvidDeJong/laravel-api-key-alert/actions/workflows/tests.yml/badge.svg)](https://github.com/ArvidDeJong/laravel-api-key-alert/actions/workflows/tests.yml)
[![PHP version](https://img.shields.io/packagist/dependency-v/darvis/laravel-api-key-alert/php.svg)](https://packagist.org/packages/darvis/laravel-api-key-alert)
[![License](https://img.shields.io/packagist/l/darvis/laravel-api-key-alert.svg)](LICENSE)

Get an email the moment an API key stops working in your Laravel app, and again when it works. The package notices the rejected key in the calls your app already makes, so a revoked OpenAI key or a Google key restricted to the wrong IP address no longer waits for a customer to complain.

## Features

- **No code changes** - Http client calls and reported exceptions are inspected for Google, OpenAI, Anthropic, Mollie and MessageBird
- **Google Maps aware** - reads the HTTP 200 `REQUEST_DENIED` answer that a status code check misses
- **One mail, not a hundred** - at most one per service per six hours, plus one when the key works again
- **Four routes, each optional** - passive detection, reported exceptions, `ApiKeyAlert::guard()` around a call, and a daily check
- **Never in the way** - the mail goes out after the response; a broken mailer never breaks a page
- **Keys stay out of the mail** - secret parameters, bearer tokens and known key formats are removed
- **Events** - `ApiKeyFailed` and `ApiKeyRecovered`, for Slack or a dashboard
- **Laravel Boost** - guideline and skill included, so an AI assistant in your app knows the API

## Requirements

- PHP 8.2 or higher
- Laravel 11, 12 or 13 (Laravel 13 itself needs PHP 8.3)
- A working mailer

## Installation

```bash
composer require darvis/laravel-api-key-alert
```

```dotenv
API_KEY_ALERT_MAIL_TO=you@example.com
```

That is all for the services the package ships. Send a test alert:

```bash
php artisan tinker --execute 'ApiKeyAlert::failed("test", "This is a test alert");'
```

## Quick start

Calls through `Http::` need nothing. For an SDK call whose exception you catch yourself, wrap it:

```php
use Darvis\LaravelApiKeyAlert\Facades\ApiKeyAlert;

$payment = ApiKeyAlert::guard('mollie', fn () => $mollie->payments->get($id));
```

The guard reports a rejected key and throws the exception again, so your own `try`/`catch` still runs.

Add an API the package does not know in `config/api-key-alert.php`:

```php
'services' => [
    'postnl' => [
        'hosts' => ['api.postnl.nl'],
    ],
],
```

Find a dead key before anyone uses it:

```dotenv
API_KEY_ALERT_CHECK=true
```

## Documentation

Full documentation: **https://arviddejong.github.io/laravel-api-key-alert/**

- [Installation](https://arviddejong.github.io/laravel-api-key-alert/installation.html)
- [How detection works](https://arviddejong.github.io/laravel-api-key-alert/detection.html)
- [Configuration](https://arviddejong.github.io/laravel-api-key-alert/configuration.html)
- [Adding a service](https://arviddejong.github.io/laravel-api-key-alert/custom-services.html)
- [Daily check](https://arviddejong.github.io/laravel-api-key-alert/daily-check.html)
- [Testing](https://arviddejong.github.io/laravel-api-key-alert/testing.html)
- [Troubleshooting](https://arviddejong.github.io/laravel-api-key-alert/troubleshooting.html)
- [FAQ](https://arviddejong.github.io/laravel-api-key-alert/faq.html)

## Laravel Boost

The package ships a [Laravel Boost](https://laravel.com/ai/boost) guideline and skill. Run `php artisan boost:install` or `boost:update` in your app to pick them up.

## Testing

```bash
composer test
composer lint
composer analyse
```

## Changelog

See [CHANGELOG](CHANGELOG.md).

## Support the package

If darvis/laravel-api-key-alert saves you time, buy me a beer 🍺: [sponsor me on GitHub](https://github.com/sponsors/ArvidDeJong).

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md).

## Security

Please report a vulnerability privately, as described in [SECURITY](SECURITY.md), not in the issue tracker.

## License

The MIT License (MIT). See [LICENSE](LICENSE).
