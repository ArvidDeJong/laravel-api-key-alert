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
php artisan api-key-alert:test
```

You should receive two mails: "[Your App] The test API key no longer works" and "[Your App] The test
API key works again". The command sends them straight away, without touching the cache or the
throttle, so it never leaves a "failing" state behind. Nothing arrives? See
[Troubleshooting](troubleshooting.md#no-mail-arrives).

## Put it on the server

The steps above were on your own machine. On the server where the app runs:

1. Add the variables to the `.env` file of the server:

    ```dotenv
    API_KEY_ALERT_MAIL_TO=you@example.com
    API_KEY_ALERT_CHECK=true
    ```

    `API_KEY_ALERT_CHECK` turns on the [daily check](daily-check.md); leave it out if you only want
    passive detection.
2. Most servers cache the config. Laravel then ignores `.env` until you rebuild that cache:

    ```bash
    php artisan config:cache
    ```

3. The daily check runs through Laravel's scheduler, so `php artisan schedule:run` has to run every
   minute. On Plesk that is a Scheduled Task of the type "Run a PHP script" on `artisan` with the
   argument `schedule:run`; elsewhere a cron line such as
   `* * * * * cd /path-to-your-app && php artisan schedule:run >> /dev/null 2>&1`.
4. Check the result:

    ```bash
    php artisan api-key-alert:test                     # the mail arrives
    php artisan schedule:list | grep api-key-alert     # the daily check is planned
    php artisan api-key-alert:check                    # every key is ok or skipped
    ```

With `API_KEY_ALERT_MAIL_QUEUE=true` the mail goes through the queue, so a queue worker has to run
as well. Leave it off if you don't know: the mail is then sent right after the response.

## Publish the config (rarely needed)

Every option has an environment variable, and a key whose variable has a different name is set in
one line in `AppServiceProvider` (see [When your key has a different name](daily-check.md#when-your-key-has-a-different-name)).
Publish the config only to add or remove services:

```bash
php artisan vendor:publish --tag=api-key-alert-config
```

A published config replaces the whole `services` list, so services that a later version adds don't
appear in your app until you copy them in.

The mail views and translations publish with `--tag=api-key-alert-views` and
`--tag=api-key-alert-translations`. The mail ships in English and Dutch and follows the app locale.

## Laravel Boost

If your app uses [Laravel Boost](https://laravel.com/ai/boost), add `"darvis/laravel-api-key-alert"`
to the `packages` list in `boost.json` and run:

```bash
php artisan boost:install --guidelines --skills --mcp --no-interaction
```

A plain `boost:update` does not pick up a new package.
