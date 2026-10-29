<?php

use Darvis\LaravelApiKeyAlert\Events\ApiKeyFailed;
use Darvis\LaravelApiKeyAlert\Mail\ApiKeyFailedMail;
use Darvis\LaravelApiKeyAlert\Mail\ApiKeyRecoveredMail;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config([
        'api-key-alert.mail.to' => 'alerts@example.test',
        'api-key-alert.services.google.check.query.key' => 'AIzaSyA1234567890abcdefghijklmnopqrstuv',
    ]);
    Mail::fake();
});

it('reports a rejected key once and exits with a failure', function () {
    Event::fake([ApiKeyFailed::class]);
    Http::fake(['maps.googleapis.com/*' => Http::response($this->googleRequestDenied())]);

    $this->artisan('api-key-alert:check', ['service' => ['google']])
        ->expectsTable(['Service', 'Status', 'Detail'], [[
            'google', 'failed', $this->googleRequestDenied()['error_message'],
        ]])
        ->assertFailed();

    // The Http listener stays quiet while the check sends its own request.
    Event::assertDispatchedTimes(ApiKeyFailed::class, 1);
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'key=AIzaSyA1234567890abcdefghijklmnopqrstuv'));
});

it('sends the token as a bearer token', function () {
    config(['api-key-alert.services.openai.check.token' => 'sk-test']);
    Http::fake(['api.openai.com/*' => Http::response(['data' => []])]);

    $this->artisan('api-key-alert:check', ['service' => ['openai']])->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer sk-test'));
});

it('skips services without a key', function () {
    $this->artisan('api-key-alert:check', ['service' => ['mollie', 'messagebird']])
        ->expectsTable(['Service', 'Status', 'Detail'], [
            ['mollie', 'skipped', ''],
            ['messagebird', 'skipped', ''],
        ])
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('sends the recovery mail when a failing key passes the check', function () {
    Http::fakeSequence('maps.googleapis.com/*')
        ->push($this->googleRequestDenied())
        ->push(['results' => [], 'status' => 'OK']);

    $this->artisan('api-key-alert:check', ['service' => ['google']])->assertFailed();
    $this->artisan('api-key-alert:check', ['service' => ['google']])->assertSuccessful();

    Mail::assertSent(ApiKeyFailedMail::class, 1);
    Mail::assertSent(ApiKeyRecoveredMail::class, 1);
});

it('does not alert when the service cannot be reached', function () {
    Http::fake(['maps.googleapis.com/*' => fn () => throw new ConnectionException('Connection timed out')]);

    $this->artisan('api-key-alert:check', ['service' => ['google']])->assertSuccessful();

    Mail::assertNothingSent();
});

it('schedules the check only when it is enabled', function () {
    $commands = fn () => collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'api-key-alert:check'));

    expect($commands())->toHaveCount(0);

    // The provider reads the switch when the schedule is built, as schedule:run does once per run.
    config(['api-key-alert.check.enabled' => true, 'api-key-alert.check.time' => '06:30']);
    app()->forgetInstance(Schedule::class);

    expect($commands())->toHaveCount(1)
        ->and($commands()->first()->expression)->toBe('30 6 * * *');
});
