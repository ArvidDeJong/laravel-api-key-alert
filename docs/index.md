---
title: Laravel API Key Alert
nav_order: 1
description: "Get an email the moment an API key stops working in your Laravel app, and again when it works, without changing your code."
permalink: /
---

# Laravel API Key Alert

An API key that stops working rarely announces itself. A Google key gets restricted to the wrong
IP address, an OpenAI key is revoked, a Mollie key is rotated, and the first sign is a customer
saying the route planner is empty. `darvis/laravel-api-key-alert` notices the rejected key in the
calls your app already makes and mails you, once, with the provider's own explanation.

```bash
composer require darvis/laravel-api-key-alert
```

```dotenv
API_KEY_ALERT_MAIL_TO=you@example.com
```

That is the whole setup for Google, OpenAI, Anthropic, Mollie and MessageBird.

## What you get

- **A mail per broken key**: the service, the provider's reason, the request (key removed), the
  server and the environment. At most one per service per six hours.
- **A mail when it works again**, so you know the fix landed.
- **Four ways to find out**, each one optional:
  passive [Http client detection](detection.md#http-client-responses),
  [reported exceptions](detection.md#reported-exceptions),
  a [guard around a call](detection.md#guarding-a-call) and a [daily check](daily-check.md).
- **Events** (`ApiKeyFailed`, `ApiKeyRecovered`) for Slack, a dashboard or a counter.
- **Two commands**: `api-key-alert:status` shows which keys are down, `api-key-alert:test` sends a
  test mail.
- **Google Maps support** that reads the HTTP 200 `REQUEST_DENIED` answer a status code check misses.
- **An [environment check](environment-check.md)** that reminds you every day while `APP_DEBUG` or
  `APP_ENV` has a value it should not have on that server, and mails once it is fixed.

## Documentation

- [Installation](installation.md)
- [How detection works](detection.md)
- [Configuration](configuration.md)
- [Adding a service](custom-services.md)
- [Daily check](daily-check.md)
- [Environment check](environment-check.md)
- [Commands](commands.md)
- [Recipes](recipes.md)
- [Testing](testing.md)
- [Troubleshooting](troubleshooting.md)
- [FAQ](faq.md)
