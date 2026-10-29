---
title: How detection works
nav_order: 3
description: "The four ways the package finds a rejected key: Http client responses, reported exceptions, a guarded call and a manual report."
---

# How detection works

Every route ends in the same place: `failed()` records the outage, fires `ApiKeyFailed` and mails
once per throttle window. A later success calls `recovered()`, which mails once and resets the window.

| Route | Finds | Switch | Code needed |
| --- | --- | --- | --- |
| Http client responses | Every `Http::` call to a host of a service | `API_KEY_ALERT_DETECT_HTTP` | none |
| Reported exceptions | SDK errors that reach `report()` | `API_KEY_ALERT_DETECT_EXCEPTIONS` | none |
| Guarded call | Errors you catch yourself | always on | `ApiKeyAlert::guard()` |
| Manual report | Anything else | always on | `ApiKeyAlert::failed()` |
| [Daily check](daily-check.md) | A dead key nobody has used yet | `API_KEY_ALERT_CHECK` | none |

## Http client responses

The package listens to the `ResponseReceived` event of the Laravel Http client. A response from a
host that belongs to no service is skipped after one string comparison. For the rest, the service's
detector decides:

- `StatusDetector` (the default): HTTP 401 or 403 is a rejected key. The message comes from the
  JSON body (`error.message`, `error_message`, `detail`, `message` and similar).
- `GoogleDetector`: also HTTP 200 with `REQUEST_DENIED` or `OVER_DAILY_LIMIT` in the body, the way
  Google Maps answers. It only decodes the body when one of those words is in it.

A 2xx response marks a failing key as working again.

## Reported exceptions

Every exception that goes through `report()`, including the ones Laravel reports for you, is
inspected:

- An exception that carries the HTTP response, such as `Illuminate\Http\Client\RequestException`,
  Guzzle's `RequestException` or Mollie's `ApiException`, goes through the detector of the service
  its host belongs to.
- An exception listed under a service's `exceptions` counts as a rejected key as it is. Use this for
  SDKs that throw without the response, such as MessageBird's `AuthenticateException`.

Exceptions in your `dontReport` list never reach this route.

## Guarding a call

When you catch an exception yourself, report() never sees it. Wrap the call instead:

```php
use Darvis\LaravelApiKeyAlert\Facades\ApiKeyAlert;

$payment = ApiKeyAlert::guard('mollie', fn () => $mollie->payments->get($id));
```

The guard returns what the callback returns and marks the key as working. When the callback throws,
a recognised key failure is reported and the exception is **thrown again**: the guard never swallows
it, so your own `try`/`catch` still runs.

To report every exception of that call, not only the recognised ones:

```php
ApiKeyAlert::guard('mollie', fn () => $mollie->payments->get($id), reportAll: true);
```

## Manual report

```php
ApiKeyAlert::failed('messagebird', 'Request not allowed (incorrect access_key)');
ApiKeyAlert::recovered('messagebird');
```

For code that talks to an API without the Http client, hand over the raw response and let the
detector decide:

```php
ApiKeyAlert::inspectResponse('google', $status, $body, $url);
```

## What happens on a failure

1. The reason and the context are stripped of keys.
2. The outage is stored in the cache, with the time it started.
3. `ApiKeyFailed` fires, on every detection, with `alerted` set to whether a mail goes out.
4. On the first detection in the throttle window: a warning in the log and the mail.

The mail is sent after the response has gone to the visitor, right away in the console, or through
the queue with `API_KEY_ALERT_MAIL_QUEUE=true`. If the mailer itself fails, that is logged; it never
breaks the request that found the failure.
