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
Scheduled Task that runs `artisan schedule:run`).

## The keys it uses

The shipped checks read these environment variables. A check whose key is empty is skipped, so
nothing is checked until you set one.

| Service | Variable | Request |
| --- | --- | --- |
| `anthropic` | `ANTHROPIC_API_KEY` | `GET /v1/models` |
| `google` | `GOOGLE_MAPS_API_KEY` | One geocode for "Amsterdam" |
| `mollie` | `MOLLIE_KEY` | `GET /v2/methods` |
| `openai` | `OPENAI_API_KEY` | `GET /v1/models` |

If your app uses a different variable, publish the config and change it:

```php
'google' => [
    'check' => [
        'query' => ['address' => 'Amsterdam', 'key' => env('GOOGLE_KEY_PHP')],
        'url' => 'https://maps.googleapis.com/maps/api/geocode/json',
    ],
    // ...
],
```

A check takes `url`, and optionally `method` (default `GET`), `query`, `headers` and `token` (sent as
a bearer token). An empty value in any of them skips the check.

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
