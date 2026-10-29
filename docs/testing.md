---
title: Testing
nav_order: 7
description: "Test an app that uses the package with Mail::fake() and Http::fake(), and keep alerts out of unrelated tests."
---

# Testing

A faked Http response goes through the same listener as a real one, so the usual fakes are enough.

```php
use Darvis\LaravelApiKeyAlert\Mail\ApiKeyFailedMail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

it('alerts when the OpenAI key is rejected', function () {
    config(['api-key-alert.mail.to' => 'alerts@example.test']);
    Mail::fake();
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key provided.']], 401)]);

    $this->post('/assistant', ['question' => 'Hello']);

    Mail::assertSent(ApiKeyFailedMail::class, fn ($mail) => $mail->service === 'openai');
});
```

Or assert on the event, which also fires without a recipient:

```php
use Darvis\LaravelApiKeyAlert\Events\ApiKeyFailed;

Event::fake([ApiKeyFailed::class]);
// ...
Event::assertDispatched(ApiKeyFailed::class, fn ($event) => $event->service === 'openai');
```

## Things to know

- The throttle lives in the cache. With the `array` cache store each test starts clean; with a
  persistent store, a second failure in another test may not mail.
- Tests that fake a 401 on purpose for other reasons will send an alert. Turn the package off for
  those with `config(['api-key-alert.enabled' => false])`.
- `Mail::fake()` skips the mailer's own events, so a mail driver that calls an API is not exercised.
- Never call a real API from a test to see the alert; `Http::preventStrayRequests()` catches a
  forgotten fake.
