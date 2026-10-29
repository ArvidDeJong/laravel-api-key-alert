---
title: Configuration
nav_order: 4
description: "Every option of config/api-key-alert.php and its environment variable, with the defaults."
---

# Configuration

All options have an environment variable, so most apps never publish the config.

| Variable | Default | Effect |
| --- | --- | --- |
| `API_KEY_ALERT_ENABLED` | `true` | Turns the whole package off. |
| `API_KEY_ALERT_MAIL_TO` | empty | One address or a comma separated list. Empty: log and events only. |
| `API_KEY_ALERT_MAIL_QUEUE` | `false` | Send the mail through the queue. |
| `API_KEY_ALERT_THROTTLE_MINUTES` | `360` | At most one mail per service in this many minutes. |
| `API_KEY_ALERT_DETECT_HTTP` | `true` | Inspect Http client responses. |
| `API_KEY_ALERT_DETECT_EXCEPTIONS` | `true` | Inspect reported exceptions. |
| `API_KEY_ALERT_CHECK` | `false` | Schedule the [daily check](daily-check.md). |
| `API_KEY_ALERT_CHECK_TIME` | `07:00` | Time of the daily check. |

## Services

Each service has a name (used in the mail and the events) and up to four settings:

```php
'services' => [
    'google' => [
        'check' => [
            'query' => ['address' => 'Amsterdam', 'key' => env('GOOGLE_MAPS_API_KEY')],
            'url' => 'https://maps.googleapis.com/maps/api/geocode/json',
        ],
        'detector' => GoogleDetector::class,
        'hosts' => ['*.googleapis.com'],
    ],
],
```

| Key | Meaning |
| --- | --- |
| `hosts` | Hosts the service answers on. `*.googleapis.com` matches every subdomain. |
| `detector` | Class that recognises a rejected key. Default `StatusDetector` (401 and 403). |
| `exceptions` | Exception classes that always mean a rejected key. |
| `check` | The request of the [daily check](daily-check.md). |

The package ships `anthropic`, `google`, `messagebird`, `mollie` and `openai`. To add your own, see
[Adding a service](custom-services.md).

## Where the state lives

The outage and the throttle are stored in the default cache store, under keys that start with
`api-key-alert:`. Use a shared store (database, Redis) when the app runs on more than one server,
or each server mails on its own. Clearing the cache forgets an outage, so no recovery mail follows.
