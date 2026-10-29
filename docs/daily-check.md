---
title: Daily check
nav_order: 6
description: "Test every key once a day with one cheap request, so a dead key is found before anyone uses it."
---

# Daily check

Passive detection only notices a dead key when someone uses it. A key for a feature that runs once
a week can be dead for days. The daily check sends one cheap request per service instead.

```dotenv
API_KEY_ALERT_CHECK=true
API_KEY_ALERT_CHECK_TIME=07:00
```

The check is added to the scheduler, so `schedule:run` has to run every minute (on Plesk: a
Scheduled Task that runs `artisan schedule:run`). The [environment check](environment-check.md) runs
in the same scheduled command, at the same time.

## The keys it uses

The shipped checks read these environment variables. A check whose key is empty is skipped, so
nothing is checked until you set one.

| Service | Variable | Request |
| --- | --- | --- |
| `anthropic` | `ANTHROPIC_API_KEY` | `GET /v1/models` |
| `google` | `GOOGLE_MAPS_API_KEY` | One geocode for "Amsterdam" |
| `mollie` | `MOLLIE_KEY` | `GET /v2/methods` |
| `openai` | `OPENAI_API_KEY` | `GET /v1/models` |

## When your key has a different name

Most apps already have the key in their own config, for example `config/services.php`:

```php
'google' => [
    'key_php' => env('GOOGLE_KEY_PHP'),
],
```

Point the check at that config key with `config:`. The value is read when the check runs, so the key
is written down once. The easiest place is the `boot()` method of `app/Providers/AppServiceProvider.php`;
you don't have to publish anything:

```php
public function boot(): void
{
    config([
        'api-key-alert.services.google.check.query.key' => 'config:services.google.key_php',
    ]);
}
```

If you have published `config/api-key-alert.php`, write it there instead:

```php
'google' => [
    'check' => [
        'query' => ['address' => 'Amsterdam', 'key' => 'config:services.google.key_php'],
        'url' => 'https://maps.googleapis.com/maps/api/geocode/json',
    ],
    // ...
],
```

`env('GOOGLE_KEY_PHP')` works there too. Keep in mind that a published config replaces the whole
`services` list: a service that a later version of the package adds does not appear in your app
until you add it yourself. That is why the `boot()` way above is the better default.

## What a check can contain

A check takes `url`, and optionally `method` (default `GET`), `query`, `headers` and `token` (sent as
a bearer token). Every value can be text, `env(...)` or a `config:` reference. An empty value in any
of them skips the check, so nothing is checked until the key is set.

## Run it by hand

```bash
php artisan api-key-alert:check
php artisan api-key-alert:check google openai
```

```text
+---------+---------+------------------------------------------------------------+
| Service | Status  | Detail                                                     |
+---------+---------+------------------------------------------------------------+
| google  | failed  | This IP, site or mobile application is not authorized ...  |
| mollie  | skipped |                                                            |
| openai  | ok      |                                                            |
+---------+---------+------------------------------------------------------------+
```

The command exits with a failure when a key is rejected. A service that cannot be reached, or
answers with something that says nothing about the key (a 500), is `inconclusive` and sends no alert.
The check follows the same throttle as the other routes.
