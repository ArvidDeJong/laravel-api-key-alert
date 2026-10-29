## darvis/laravel-api-key-alert

Mails `API_KEY_ALERT_MAIL_TO` when an API key is rejected, at most once per service per throttle window (6 hours), and once more when the key works again.

- Calls through the Laravel `Http::` client and reported exceptions are inspected automatically for the services in `config/api-key-alert.php` (anthropic, google, messagebird, mollie, openai). Don't add code for those.
- For a call whose exception you catch yourself, wrap it: `ApiKeyAlert::guard('mollie', fn () => ...)`. The guard always throws the exception again; never rely on it to swallow one, and keep your own `try`/`catch` around it.
- For an API without the Http client and without a reported exception, call `ApiKeyAlert::failed($service, $reason)` and `ApiKeyAlert::recovered($service)`.
- A new API is a new entry under `services` with its `hosts`. Never hardcode a key check in app code for it.
- Google Maps answers a rejected key with HTTP 200 and `REQUEST_DENIED`; the shipped `GoogleDetector` handles that. Don't "fix" it with a status code check.
- Never put an API key in the reason or context you pass in; the package strips common key formats, but not every one.
- In tests: `Mail::fake()` + `Http::fake()`, then assert `ApiKeyFailedMail` or fake the `ApiKeyFailed` event. Tests that fake a 401 for another reason send an alert; turn it off there with `config(['api-key-alert.enabled' => false])`.
- Events: `ApiKeyFailed` (every detection, `alerted` tells whether a mail went out) and `ApiKeyRecovered`.

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
