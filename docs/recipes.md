---
title: Recipes
nav_order: 9
description: "Working examples: an alert in Slack, a check for spatie/laravel-health, a warning in your admin, and a deploy that stops on a dead key."
---

# Recipes

Everything the package knows comes out through the two events and `ApiKeyAlert::failure()`. These
examples build on that; copy what fits.

## An alert in Slack

Listen to the events and post to an incoming webhook. `ApiKeyFailed` fires on every detection; `alerted`
is true only for the one that also mailed, so you get one Slack message per throttle window too.

```php
// app/Listeners/PostApiKeyAlertToSlack.php
namespace App\Listeners;

use Darvis\LaravelApiKeyAlert\Events\ApiKeyFailed;
use Darvis\LaravelApiKeyAlert\Events\ApiKeyRecovered;
use Illuminate\Support\Facades\Http;

class PostApiKeyAlertToSlack
{
    public function handle(ApiKeyFailed|ApiKeyRecovered $event): void
    {
        if ($event instanceof ApiKeyFailed && ! $event->alerted) {
            return;
        }

        $text = $event instanceof ApiKeyFailed
            ? ":rotating_light: {$event->service} rejected the API key: {$event->reason}"
            : ":white_check_mark: {$event->service} accepts the API key again";

        Http::post(config('services.slack.alerts_webhook'), ['text' => $text]);
    }
}
```

```php
// app/Providers/AppServiceProvider.php
use Darvis\LaravelApiKeyAlert\Events\ApiKeyFailed;
use Darvis\LaravelApiKeyAlert\Events\ApiKeyRecovered;
use Illuminate\Support\Facades\Event;

public function boot(): void
{
    Event::listen([ApiKeyFailed::class, ApiKeyRecovered::class], PostApiKeyAlertToSlack::class);
}
```

The reason is already redacted. Leave `API_KEY_ALERT_MAIL_TO` empty if Slack is enough; the events fire
without a recipient.

## A check for spatie/laravel-health

If the app already uses [spatie/laravel-health](https://spatie.be/docs/laravel-health), one check
puts the keys on the same dashboard and in the same notifications.

```php
// app/Health/ApiKeysCheck.php
namespace App\Health;

use Darvis\LaravelApiKeyAlert\Facades\ApiKeyAlert;
use Darvis\LaravelApiKeyAlert\Support\ApiKeyAlertConfig;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class ApiKeysCheck extends Check
{
    public function run(): Result
    {
        $failing = array_filter(ApiKeyAlertConfig::services(), fn (string $service) => ApiKeyAlert::isFailing($service));

        return $failing === []
            ? Result::make()->ok('All API keys are accepted')
            : Result::make()->failed('Rejected API key: '.implode(', ', $failing));
    }
}
```

```php
// app/Providers/AppServiceProvider.php
use App\Health\ApiKeysCheck;
use Spatie\Health\Facades\Health;

Health::checks([
    ApiKeysCheck::new(),
]);
```

The check reads the cache, so it is free to run every minute.

## A warning in your admin

For a notice at the top of an admin layout, ask the package in the view. `failure()` returns null
when the key works, so the block renders nothing most of the time.

{% raw %}
```blade
@if ($failure = \Darvis\LaravelApiKeyAlert\Facades\ApiKeyAlert::failure('google'))
    <div class="rounded bg-red-50 p-4 text-red-800">
        The Google Maps key has been rejected since {{ $failure->since }}: {{ $failure->reason }}
    </div>
@endif
```
{% endraw %}

## A deploy that stops on a dead key

`api-key-alert:status` exits with a failure when a key is down, so a deploy script can refuse to
continue, and `api-key-alert:check` tests the keys against the live APIs first:

```bash
php artisan api-key-alert:check && php artisan api-key-alert:status
```

For a monitoring tool that wants data, `--json` gives it:

```bash
php artisan api-key-alert:status --json
```
