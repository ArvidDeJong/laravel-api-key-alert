---
title: Environment check
nav_order: 7
description: "A daily reminder while APP_DEBUG or APP_ENV has a value it should not have on a server, and a mail once it is fixed."
---

# Environment check

A deploy goes fine, and a week later someone notices that `APP_DEBUG` has been on the whole time, or
that `APP_ENV` still says `local` and the production mail driver never kicked in. The environment
check mails you the next morning instead.

```dotenv
API_KEY_ALERT_ENVIRONMENT_CHECK=true
```

Set it in the `.env` of the server where the values must hold, usually production. The check runs
with the [daily check](daily-check.md), at `API_KEY_ALERT_CHECK_TIME` (default `07:00`), and needs
the scheduler to run. It compares the live config with the expected values:

| Setting | Expected |
| --- | --- |
| `app.debug` | `false` |
| `app.env` | `production` |

Every daily check that still finds a wrong value mails again, so the reminder keeps coming until it
is fixed. Once everything is right you get one more mail saying so. The mail names each setting with
its actual and its expected value, and tells you to run `php artisan config:clear` after the fix.

## Changing the expected values

Publish the config and edit `environment.expected`. Any config key works, and the comparison is
strict:

```php
'environment' => [
    'check' => (bool) env('API_KEY_ALERT_ENVIRONMENT_CHECK', false),
    'expected' => [
        'app.debug' => false,
        'app.env' => 'production',
        'mail.default' => 'ses',
    ],
],
```

Keep the list short. Every extra key is one more thing that differs on staging, and the check only
helps while you trust it.

## Running it by hand

```bash
php artisan api-key-alert:check environment
php artisan api-key-alert:status
```

The first runs the check now, also when `API_KEY_ALERT_ENVIRONMENT_CHECK` is off. The second shows
whether the environment is currently reported as wrong, without a check.

## What it does not catch

The switch lives in `.env`, so a server whose whole `.env` is replaced by a `.env.example` loses the
switch together with the right values, and the check stays silent. The package does not guess whether
a server is production; that guess would be wrong exactly when you need it. If that is a risk in your
deploy, keep the switch in the deploy template too.

## In code

The check is a service called `environment`, so everything that works for a key works here too:
`ApiKeyAlert::isFailing('environment')`, `ApiKeyAlert::failure('environment')`, the `ApiKeyFailed`
and `ApiKeyRecovered` events, and the Slack recipe in [Recipes](recipes.md). The mails are
`EnvironmentFailedMail` and `EnvironmentRecoveredMail`, in English and Dutch, and publishable like
the others. `ApiKeyAlert::checkEnvironment()` runs the comparison and returns a `CheckResult`.
