---
name: laravel-api-key-alert-development
description: Work with darvis/laravel-api-key-alert. Use it to get alerted when an API key stops working, add an API to the watched services, write a detector, wrap an SDK call in a guard, test code that triggers an alert, and react to the ApiKeyFailed and ApiKeyRecovered events.
---

# darvis/laravel-api-key-alert development

## When to use this skill

Use this skill in an application that has `darvis/laravel-api-key-alert` installed when you add or
change a call to an external API, when an API key alert arrives or fails to arrive, when a test
sends an unexpected alert mail, when someone asks which keys are down, or when you add an API the
package does not know yet.

## Commands

| Command | Does | Costs |
| --- | --- | --- |
| `api-key-alert:status [--json]` | Lists every service with `ok` or `failing`, since when and why; exits 1 when a key is failing | Reads the cache only |
| `api-key-alert:test [service]` | Sends a test alert and a test recovery mail straight away, without cache, throttle, queue or events | One mail |
| `api-key-alert:check [service...]` | Sends one request per service with a `check` and reports `ok`, `failed`, `skipped` or `inconclusive`; includes `environment` when that check is on, or when named | One API call per service |

Start with `api-key-alert:status` when a user asks about the state of a key, and with
`api-key-alert:test` when the question is whether the mail works at all. In code,
`ApiKeyAlert::failure($service)` returns a `Failure` (`service`, `reason`, `since`) or null.

## How it works

| Route | Trigger | Switch | Recognised as a rejected key |
| --- | --- | --- | --- |
| Http client | `ResponseReceived` of every `Http::` call to a host of a service | `api-key-alert.detect.http` | The service's detector returns a reason |
| Reported exception | Every exception passed to `report()` | `api-key-alert.detect.exceptions` | Its PSR-7 or Laravel response fails the detector, or its class is in the service's `exceptions` |
| Guard | `ApiKeyAlert::guard($service, $callback)` | always | Same as above; with `reportAll: true` any exception |
| Manual | `ApiKeyAlert::failed($service, $reason)` | always | Always |
| Daily check | `api-key-alert:check`, scheduled when `api-key-alert.check.enabled` | `api-key-alert.check.enabled` | The detector, on one request per service |

Detectors: `StatusDetector` (default) reports HTTP 401 and 403. `GoogleDetector` also reports HTTP 200
with `REQUEST_DENIED` or `OVER_DAILY_LIMIT` in the body.

After a detection: the reason and context are stripped of keys, the outage is cached
(`api-key-alert:down:{service}`, 30 days), `ApiKeyFailed` fires, and only the first detection in the
throttle window (`api-key-alert:alerted:{service}`) logs a warning and mails. A later 2xx or a passing
guard or check fires `ApiKeyRecovered`, mails once and resets the window.

## Environment check

`API_KEY_ALERT_ENVIRONMENT_CHECK=true` makes the daily check compare config keys with expected
values (`app.debug` => false, `app.env` => 'production' by default; edit `environment.expected` in
the published config to add keys, the comparison is strict). A wrong value mails
`EnvironmentFailedMail` on every daily check until it is fixed, then `EnvironmentRecoveredMail` once.
The alert is the service `environment`, so `isFailing('environment')`, `failure('environment')` and
the events work. Don't configure a service with that name. `ApiKeyAlert::checkEnvironment()` runs it.

## Adding an API

```php
// config/api-key-alert.php
'services' => [
    'postnl' => [
        'hosts' => ['api.postnl.nl'],
    ],
],
```

For an SDK that throws without the response, list its exception class under `exceptions`. For an API
that signals a rejected key another way, extend `StatusDetector`, override `detect(int $status, string $body): ?string`
and set it as `detector`. Return early: it runs on every response from those hosts.

## Guarding a call

```php
$result = ApiKeyAlert::guard('mollie', fn () => $mollie->payments->get($id));
```

The guard returns the callback's result and throws every exception again. Never write code that
expects the guard to return null on failure.

## Pitfalls

- Clearing the cache forgets an outage: no recovery mail follows.
- A `file` cache store on several servers means one alert per server. Use a shared store.
- `API_KEY_ALERT_MAIL_QUEUE=true` needs a running queue worker; the daily check needs `schedule:run`.
- A check whose key variable is empty is skipped, not failed. The shipped checks read
  `GOOGLE_MAPS_API_KEY`, `OPENAI_API_KEY`, `ANTHROPIC_API_KEY` and `MOLLIE_KEY`; point them at the
  variables the app really uses.
- An API that answers 403 for a missing permission gives false alarms with the default detector.

## Testing

```php
config(['api-key-alert.mail.to' => 'alerts@example.test']);
Mail::fake();
Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key provided.']], 401)]);

// ... run the code that calls the API

Mail::assertSent(\Darvis\LaravelApiKeyAlert\Mail\ApiKeyFailedMail::class);
```

- Use the `array` cache store, or one test's throttle stops the next test's mail.
- A test that fakes a 401 for another reason sends an alert; set `api-key-alert.enabled` to false there.
- Don't call a real API to see an alert; fake the response.
