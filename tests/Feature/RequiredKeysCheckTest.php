<?php

use Darvis\LaravelApiKeyAlert\Events\ApiKeyFailed;
use Darvis\LaravelApiKeyAlert\Facades\ApiKeyAlert;
use Darvis\LaravelApiKeyAlert\Mail\ApiKeyFailedMail;
use Darvis\LaravelApiKeyAlert\Mail\MissingKeysFailedMail;
use Darvis\LaravelApiKeyAlert\Mail\MissingKeysRecoveredMail;
use Darvis\LaravelApiKeyAlert\Support\CheckStatus;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config([
        'api-key-alert.mail.to' => 'alerts@example.test',
        'api-key-alert.required.check' => true,
        'api-key-alert.required.keys' => [
            'MAXMIND_LICENSE_KEY' => 'set-value-1',
            'MESSAGE_BIRD_KEY' => 'set-value-2',
        ],
    ]);
    Mail::fake();
});

it('mails which keys are missing and reminds on every check until they are set', function () {
    config(['api-key-alert.required.keys.MESSAGE_BIRD_KEY' => null, 'api-key-alert.required.keys.MAXMIND_LICENSE_KEY' => '  ']);

    $result = ApiKeyAlert::checkRequiredKeys();

    expect($result->status)->toBe(CheckStatus::Failed)
        ->and($result->detail)->toBe('Missing: MAXMIND_LICENSE_KEY, MESSAGE_BIRD_KEY')
        ->and(ApiKeyAlert::isFailing('missing-keys'))->toBeTrue();

    Mail::assertSent(MissingKeysFailedMail::class, fn (MissingKeysFailedMail $mail) => $mail->context['keys'] === ['MAXMIND_LICENSE_KEY', 'MESSAGE_BIRD_KEY']);
    Mail::assertNotSent(ApiKeyFailedMail::class);

    // The next daily check is outside the throttle window, so it mails again: that is the reminder.
    $this->travel(1)->day();
    ApiKeyAlert::checkRequiredKeys();

    Mail::assertSent(MissingKeysFailedMail::class, 2);

    config(['api-key-alert.required.keys.MESSAGE_BIRD_KEY' => 'set', 'api-key-alert.required.keys.MAXMIND_LICENSE_KEY' => 'set']);

    expect(ApiKeyAlert::checkRequiredKeys()->status)->toBe(CheckStatus::Ok)
        ->and(ApiKeyAlert::isFailing('missing-keys'))->toBeFalse();

    Mail::assertSent(MissingKeysRecoveredMail::class, 1);
});

it('reads a config reference when the check runs', function () {
    config([
        'services.maxmind.license_key' => null,
        'api-key-alert.required.keys' => ['MaxMind' => 'config:services.maxmind.license_key'],
    ]);

    expect(ApiKeyAlert::checkRequiredKeys()->detail)->toBe('Missing: MaxMind');

    config(['services.maxmind.license_key' => 'abc']);

    expect(ApiKeyAlert::checkRequiredKeys()->status)->toBe(CheckStatus::Ok);
});

it('renders both mails with the names and never a value', function () {
    config(['api-key-alert.required.keys.MESSAGE_BIRD_KEY' => '']);
    ApiKeyAlert::checkRequiredKeys();

    Mail::assertSent(MissingKeysFailedMail::class, function (MissingKeysFailedMail $mail) {
        $html = $mail->render();

        return str_contains($html, 'API keys of Test App are missing')
            && str_contains($html, 'MESSAGE_BIRD_KEY')
            && ! str_contains($html, 'MAXMIND_LICENSE_KEY')
            && ! str_contains($html, 'set-value-1')
            && ! str_contains($html, 'keys:</strong>');
    });

    config(['api-key-alert.required.keys.MESSAGE_BIRD_KEY' => 'set']);
    ApiKeyAlert::checkRequiredKeys();

    Mail::assertSent(MissingKeysRecoveredMail::class, fn (MissingKeysRecoveredMail $mail) => str_contains($mail->render(), 'is set again'));
});

it('fires the events with the missing-keys service name', function () {
    Event::fake([ApiKeyFailed::class]);
    config(['api-key-alert.required.keys.MESSAGE_BIRD_KEY' => '']);

    ApiKeyAlert::checkRequiredKeys();

    Event::assertDispatched(ApiKeyFailed::class, fn (ApiKeyFailed $event) => $event->service === 'missing-keys');
});

it('does nothing without required keys', function () {
    config(['api-key-alert.required.keys' => []]);

    expect(ApiKeyAlert::checkRequiredKeys()->status)->toBe(CheckStatus::Skipped);

    Mail::assertNothingSent();
});

it('runs with the daily check only when it is enabled', function () {
    config(['api-key-alert.required.keys.MESSAGE_BIRD_KEY' => '']);

    $this->artisan('api-key-alert:check')
        ->expectsTable(['Service', 'Status', 'Detail'], [
            ['anthropic', 'skipped', ''],
            ['google', 'skipped', ''],
            ['messagebird', 'skipped', ''],
            ['mollie', 'skipped', ''],
            ['openai', 'skipped', ''],
            ['missing-keys', 'failed', 'Missing: MESSAGE_BIRD_KEY'],
        ])
        ->assertFailed();

    config(['api-key-alert.required.check' => false]);

    $this->artisan('api-key-alert:check')->assertSuccessful();

    // Asking for it by name works even when the daily run leaves it out.
    $this->artisan('api-key-alert:check', ['service' => ['missing-keys']])->assertFailed();

    Http::assertNothingSent();
});

it('shows the missing keys in the status', function () {
    config(['api-key-alert.required.keys.MESSAGE_BIRD_KEY' => '']);
    ApiKeyAlert::checkRequiredKeys();

    $this->artisan('api-key-alert:status')
        ->expectsTable(['Service', 'Status', 'Since', 'Reason'], [
            ['anthropic', 'ok', '', ''],
            ['google', 'ok', '', ''],
            ['messagebird', 'ok', '', ''],
            ['mollie', 'ok', '', ''],
            ['openai', 'ok', '', ''],
            ['missing-keys', 'failing', now()->toDateTimeString(), 'Missing: MESSAGE_BIRD_KEY'],
        ])
        ->assertFailed();
});

it('schedules the check for the required keys check alone', function () {
    config(['api-key-alert.check.enabled' => false]);
    app()->forgetInstance(Schedule::class);

    $commands = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'api-key-alert:check'));

    expect($commands)->toHaveCount(1);
});
