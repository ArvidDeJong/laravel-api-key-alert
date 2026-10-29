---
title: Installation
nav_order: 2
description: "Install darvis/laravel-api-key-alert with Composer, set the recipient and send a test alert."
---

# Installation

Requires PHP 8.2+, Laravel 11, 12 or 13, and a working mailer.

```bash
composer require darvis/laravel-api-key-alert
```

The service provider and the `ApiKeyAlert` facade register themselves.

## Set the recipient

```dotenv
API_KEY_ALERT_MAIL_TO=you@example.com
```

Several addresses go in one comma separated value:

```dotenv
API_KEY_ALERT_MAIL_TO="you@example.com, ops@example.com"
```

Without an address the package still logs each alert and fires its events, but sends no mail.

## Send a test alert

```bash
php artisan tinker --execute 'ApiKeyAlert::failed("test", "This is a test alert");'
```

You should receive "[Your App] The test API key no longer works". Clear it again, which also sends
the recovery mail:

```bash
php artisan tinker --execute 'ApiKeyAlert::recovered("test");'
```

## Publish the config (optional)

Only needed to change a service, add one, or turn on the [daily check](daily-check.md):

```bash
php artisan vendor:publish --tag=api-key-alert-config
```

The mail views and translations publish with `--tag=api-key-alert-views` and
`--tag=api-key-alert-translations`. The mail ships in English and Dutch and follows the app locale.

## Running a queue or on Plesk

With `API_KEY_ALERT_MAIL_QUEUE=true` the mail goes through the queue, so a worker has to run.
The [daily check](daily-check.md) needs `schedule:run` every minute. Without either, keep the
defaults: the mail is then sent right after the response.
