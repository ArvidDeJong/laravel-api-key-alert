---
title: Commands
nav_order: 8
description: "The three artisan commands: api-key-alert:status to see which keys are down, api-key-alert:test to send a test mail, and api-key-alert:check to test the keys now."
---

# Commands

## See which keys are down

```bash
php artisan api-key-alert:status
```

```text
+-------------+---------+---------------------+------------------------------------------------+
| Service     | Status  | Since               | Reason                                         |
+-------------+---------+---------------------+------------------------------------------------+
| anthropic   | ok      |                     |                                                |
| google      | failing | 2026-10-29 06:12:40 | This IP, site or mobile application is not ... |
| messagebird | ok      |                     |                                                |
| mollie      | ok      |                     |                                                |
| openai      | ok      |                     |                                                |
+-------------+---------+---------------------+------------------------------------------------+
```

The command reads the cache, so it costs no API calls and answers at once. It exits with a failure
when any key is failing, so a cron job or a deploy script can stop on it. `--json` prints the same
as JSON, for a dashboard or a monitoring tool:

```bash
php artisan api-key-alert:status --json
```

```json
{
    "anthropic": {
        "status": "ok",
        "since": null,
        "reason": null
    },
    "google": {
        "status": "failing",
        "since": "2026-10-29 06:12:40",
        "reason": "This IP, site or mobile application is not authorized to use this API key. ..."
    }
}
```

In code, `ApiKeyAlert::failure('google')` returns a `Failure` object (`service`, `reason`, `since`)
or null, and `ApiKeyAlert::isFailing('google')` the bare yes or no.

## Send a test mail

```bash
php artisan api-key-alert:test
php artisan api-key-alert:test google
```

Sends a test alert and a test recovery mail to `API_KEY_ALERT_MAIL_TO`, straight away and without
the queue, the cache, the throttle or the events. It leaves nothing behind, so running it on a
production server is safe. Without recipients, or when the mailer fails, it exits with a failure and
tells you why. The optional service name only changes the name in the mails.

## Test the keys now

```bash
php artisan api-key-alert:check
php artisan api-key-alert:check google openai
```

Sends one request per service that has a `check` and reports the result. This is the command the
[daily check](daily-check.md) schedules; that page has the details and the keys it uses. When the
[environment check](environment-check.md) is on, a row `environment` is part of the full run; ask for
it alone with `api-key-alert:check environment`, which also works while the check is off. The
`environment` row shows up in `api-key-alert:status` as well.
