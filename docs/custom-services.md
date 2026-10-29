---
title: Adding a service
nav_order: 5
description: "Add an API the package does not know, with its hosts, its exceptions and a detector for APIs that signal a rejected key their own way."
---

# Adding a service

Publish the config and add an entry under `services`. The name is free; it appears in the mail.

```php
'services' => [
    // ...
    'postnl' => [
        'hosts' => ['api.postnl.nl'],
    ],
],
```

Every Http client response from `api.postnl.nl` with HTTP 401 or 403 now sends an alert.

## An SDK that throws its own exception

If the SDK throws without the HTTP response attached, list its exception:

```php
'sendcloud' => [
    'exceptions' => [\Sendcloud\Exceptions\AuthenticationException::class],
    'hosts' => ['panel.sendcloud.sc'],
],
```

The class name is compared with `instanceof`, so the SDK does not have to be installed for the
config to load.

## An API that signals a rejected key differently

Write a detector. It gets the status and the body, and returns the reason or `null`. It runs on every
response from the service's hosts, so return early on the common case:

```php
namespace App\ApiKeyAlert;

use Darvis\LaravelApiKeyAlert\Detectors\StatusDetector;

class ExampleDetector extends StatusDetector
{
    public function detect(int $status, string $body): ?string
    {
        $reason = parent::detect($status, $body);

        if ($reason !== null || $status !== 200 || ! str_contains($body, 'INVALID_KEY')) {
            return $reason;
        }

        return $this->messageFrom($body) ?? 'INVALID_KEY';
    }
}
```

```php
'example' => [
    'detector' => \App\ApiKeyAlert\ExampleDetector::class,
    'hosts' => ['api.example.com'],
],
```

Extending `StatusDetector` keeps the 401/403 handling and gives you `messageFrom()`, which reads the
error message out of the common JSON shapes. Implementing `Detector` directly is fine too.

## Watch out with 403

Some APIs answer 403 for "this key may not do this" rather than "this key is invalid". If that
gives false alarms, write a detector that only reports 401, or reads the error code from the body.
