## darvis/laravel-api-key-alert

Mails `API_KEY_ALERT_MAIL_TO` when an API key is rejected, at most once per service per throttle window (6 hours), and once more when the key works again.

- Calls through the Laravel `Http::` client and reported exceptions are inspected automatically for the services in `config/api-key-alert.php` (anthropic, google, messagebird, mollie, openai). Don't add code for those.
- For a call whose exception you catch yourself, wrap it: `ApiKeyAlert::guard('mollie', fn () => ...)`. The guard always throws the exception again; never rely on it to swallow one, and keep your own `try`/`catch` around it.
- For an API without the Http client and without a reported exception, call `ApiKeyAlert::failed($service, $reason)` and `ApiKeyAlert::recovered($service)`.
- A new API is a new entry under `services` with its `hosts`. Never hardcode a key check in app code for it.
- When the app keeps a key under another name (say `services.google.key_php`), point the daily check at it in `AppServiceProvider::boot()`: `config(['api-key-alert.services.google.check.query.key' => 'config:services.google.key_php'])`. Don't publish the config just for that: a published config replaces the whole `services` list and freezes it.
- Google Maps answers a rejected key with HTTP 200 and `REQUEST_DENIED`; the shipped `GoogleDetector` handles that. Don't "fix" it with a status code check.
- Never put an API key in the reason or context you pass in; the package strips common key formats, but not every one.
- In tests: `Mail::fake()` + `Http::fake()`, then assert `ApiKeyFailedMail` or fake the `ApiKeyFailed` event. Tests that fake a 401 for another reason send an alert; turn it off there with `config(['api-key-alert.enabled' => false])`.
- Events: `ApiKeyFailed` (every detection, `alerted` tells whether a mail went out) and `ApiKeyRecovered`.
- `API_KEY_ALERT_ENVIRONMENT_CHECK=true` adds a daily check that `app.debug` is false and `app.env` is `production` (the expected values are in `config/api-key-alert.php` under `environment.expected`); it mails again every day until fixed. Its alerts use the service name `environment`, which is reserved.
- To see which keys are down, run `php artisan api-key-alert:status` (`--json` for data) or call `ApiKeyAlert::failure($service)`; it reads the cache and costs no API calls. `php artisan api-key-alert:check` tests the keys against the live APIs. `php artisan api-key-alert:test` sends a test mail without touching the cache; use it when a user asks whether the mail works.

@verbatim
<code-snippet name="Guard an SDK call" lang="php">
use Darvis\LaravelApiKeyAlert\Facades\ApiKeyAlert;

try {
    $payment = ApiKeyAlert::guard('mollie', fn () => $mollie->payments->get($id));
} catch (\Mollie\Api\Exceptions\ApiException $e) {
    // Still runs: the guard reported the rejected key and threw the exception again.
    return back()->withErrors(['payment' => 'Payment status unavailable.']);
}
</code-snippet>
@endverbatim
