<?php

use Darvis\LaravelApiKeyAlert\Events\ApiKeyFailed;
use Darvis\LaravelApiKeyAlert\Facades\ApiKeyAlert;
use Darvis\LaravelApiKeyAlert\Mail\ApiKeyFailedMail;
use Darvis\LaravelApiKeyAlert\Mail\EnvironmentFailedMail;
use Darvis\LaravelApiKeyAlert\Mail\EnvironmentRecoveredMail;
use Darvis\LaravelApiKeyAlert\Support\CheckStatus;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    // Testbench runs as "testing" with debug on; start from a server that is right and break it per test.
    config([
        'api-key-alert.mail.to' => 'alerts@example.test',
        'api-key-alert.environment.check' => true,
        'app.debug' => false,
        'app.env' => 'production',
    ]);
    Mail::fake();
});

it('mails which settings are wrong and reminds on every check until they are right', function () {
    config(['app.debug' => true, 'app.env' => 'local']);

    $result = ApiKeyAlert::checkEnvironment();

    expect($result->status)->toBe(CheckStatus::Failed)
        ->and($result->detail)->toBe('app.debug is true, expected false; app.env is "local", expected "production"')
        ->and(ApiKeyAlert::isFailing('environment'))->toBeTrue();

    Mail::assertSent(EnvironmentFailedMail::class, fn (EnvironmentFailedMail $mail) => $mail->context['settings'] === [
        'app.debug' => ['actual' => true, 'expected' => false],
        'app.env' => ['actual' => 'local', 'expected' => 'production'],
    ]);
    Mail::assertNotSent(ApiKeyFailedMail::class);

    // The next daily check is outside the throttle window, so it mails again: that is the reminder.
    $this->travel(1)->day();
    ApiKeyAlert::checkEnvironment();

    Mail::assertSent(EnvironmentFailedMail::class, 2);

    config(['app.debug' => false, 'app.env' => 'production']);

    expect(ApiKeyAlert::checkEnvironment()->status)->toBe(CheckStatus::Ok)
        ->and(ApiKeyAlert::isFailing('environment'))->toBeFalse();

    Mail::assertSent(EnvironmentRecoveredMail::class, 1);
});

it('renders both environment mails', function () {
    config(['app.debug' => true]);
    ApiKeyAlert::checkEnvironment();

    Mail::assertSent(EnvironmentFailedMail::class, function (EnvironmentFailedMail $mail) {
        $html = $mail->render();

        return str_contains($html, 'The environment settings of Test App are wrong')
            && str_contains($html, 'app.debug')
            && str_contains($html, 'is true, expected false')
            && ! str_contains($html, 'settings:');
    });

    config(['app.debug' => false]);
    ApiKeyAlert::checkEnvironment();

    Mail::assertSent(EnvironmentRecoveredMail::class, fn (EnvironmentRecoveredMail $mail) => str_contains($mail->render(), 'right again'));
});

it('fires the events with the environment service name', function () {
    Event::fake([ApiKeyFailed::class]);
    config(['app.env' => 'local']);

    ApiKeyAlert::checkEnvironment();

    Event::assertDispatched(ApiKeyFailed::class, fn (ApiKeyFailed $event) => $event->service === 'environment');
});

it('does nothing without expected settings', function () {
    config(['api-key-alert.environment.expected' => []]);

    expect(ApiKeyAlert::checkEnvironment()->status)->toBe(CheckStatus::Skipped);

    Mail::assertNothingSent();
});

it('runs with the daily check only when it is enabled', function () {
    config(['app.env' => 'local']);

    $this->artisan('api-key-alert:check', ['service' => ['mollie']])
        ->expectsTable(['Service', 'Status', 'Detail'], [['mollie', 'skipped', '']])
        ->assertSuccessful();

    $this->artisan('api-key-alert:check')
        ->expectsTable(['Service', 'Status', 'Detail'], [
            ['anthropic', 'skipped', ''],
            ['google', 'skipped', ''],
            ['messagebird', 'skipped', ''],
            ['mollie', 'skipped', ''],
            ['openai', 'skipped', ''],
            ['environment', 'failed', 'app.env is "local", expected "production"'],
        ])
        ->assertFailed();

    config(['api-key-alert.environment.check' => false]);

    $this->artisan('api-key-alert:check')->assertSuccessful();

    // Asking for it by name works even when the daily run leaves it out.
    $this->artisan('api-key-alert:check', ['service' => ['environment']])->assertFailed();

    Http::assertNothingSent();
});

it('shows the environment in the status', function () {
    config(['app.debug' => true]);
    ApiKeyAlert::checkEnvironment();

    $this->artisan('api-key-alert:status')
        ->expectsTable(['Service', 'Status', 'Since', 'Reason'], [
            ['anthropic', 'ok', '', ''],
            ['google', 'ok', '', ''],
            ['messagebird', 'ok', '', ''],
            ['mollie', 'ok', '', ''],
            ['openai', 'ok', '', ''],
            ['environment', 'failing', now()->toDateTimeString(), 'app.debug is true, expected false'],
        ])
        ->assertFailed();
});

it('schedules the check for the environment check alone', function () {
    config(['api-key-alert.check.enabled' => false]);
    app()->forgetInstance(Schedule::class);

    $commands = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'api-key-alert:check'));

    expect($commands)->toHaveCount(1);
});
