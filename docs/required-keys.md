---
title: Missing keys
nav_order: 8
description: "A daily reminder while an API key the app needs is empty on a server, and a mail once every key is set."
---

# Missing keys

The [daily check](daily-check.md) skips a service whose key is empty, because asking with an empty
key would only give a false alarm. That also means a key that was never set on a new server, or that
went missing from `.env` in a deploy, stays silent until a visitor runs into it. The required keys
check mails you the next morning instead.

```dotenv
API_KEY_ALERT_REQUIRED_CHECK=true
```

Then list the keys the app needs in `required.keys` of the published config. The name is what the
mail shows; the value is what is checked:

```php
'required' => [
    'check' => (bool) env('API_KEY_ALERT_REQUIRED_CHECK', false),
    'keys' => [
        'MAXMIND_LICENSE_KEY' => env('MAXMIND_LICENSE_KEY'),
        'MESSAGE_BIRD_KEY' => env('MESSAGE_BIRD_KEY'),
        'Google Maps' => 'config:services.google.key',
    ],
],
```

Use `env('...')` in the config file, so the value is read while the config is cached and the check
also works with `php artisan config:cache`. A `config:` reference reads a config key of the app when
the check runs, like it does in a [daily check](daily-check.md). A value that is empty, `null` or only
spaces counts as missing.

The check runs with the daily check, at `API_KEY_ALERT_CHECK_TIME` (default `07:00`), and needs the
scheduler to run. Every daily check that still finds a missing key mails again, so the reminder keeps
coming until the key is set. Once every key has a value you get one more mail saying so. The mail
only names the keys. It never contains a value.

## Running it by hand

```bash
php artisan api-key-alert:check missing-keys
php artisan api-key-alert:status
```

The first runs the check now, also when `API_KEY_ALERT_REQUIRED_CHECK` is off. The second shows
whether keys are currently reported as missing, without a check.

## What it does not catch

A key that is set but wrong is not missing; the [passive detection](detection.md) and the daily check
find that one. And like the [environment check](environment-check.md), the switch lives in `.env`: a
server whose whole `.env` is gone loses the switch too. Keep `API_KEY_ALERT_REQUIRED_CHECK=true` in
the deploy template if that is a risk.

## In code

The check is a service called `missing-keys`, so `ApiKeyAlert::isFailing('missing-keys')`,
`ApiKeyAlert::failure('missing-keys')`, the `ApiKeyFailed` and `ApiKeyRecovered` events and the Slack
recipe in [Recipes](recipes.md) work for it. The mails are `MissingKeysFailedMail` and
`MissingKeysRecoveredMail`, in English and Dutch, and publishable like the others.
`ApiKeyAlert::checkRequiredKeys()` runs the check and returns a `CheckResult`.
