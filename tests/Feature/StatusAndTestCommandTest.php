<?php

use Darvis\LaravelApiKeyAlert\Events\ApiKeyFailed;
use Darvis\LaravelApiKeyAlert\Events\ApiKeyRecovered;
use Darvis\LaravelApiKeyAlert\Facades\ApiKeyAlert;
use Darvis\LaravelApiKeyAlert\Mail\ApiKeyFailedMail;
use Darvis\LaravelApiKeyAlert\Mail\ApiKeyRecoveredMail;
use Darvis\LaravelApiKeyAlert\Support\Failure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['api-key-alert.mail.to' => 'alerts@example.test']);
    Mail::fake();
});

it('shows every service as ok when no key failed', function () {
    $this->artisan('api-key-alert:status')
        ->expectsTable(['Service', 'Status', 'Since', 'Reason'], [
            ['anthropic', 'ok', '', ''],
            ['google', 'ok', '', ''],
            ['messagebird', 'ok', '', ''],
            ['mollie', 'ok', '', ''],
            ['openai', 'ok', '', ''],
        ])
        ->assertSuccessful();
});

it('shows a failing key with its reason and exits with a failure', function () {
    $this->travelTo('2026-10-29 09:00:00');
    ApiKeyAlert::failed('openai', 'Incorrect API key provided: sk-abc123def456ghi789');

    $this->artisan('api-key-alert:status')
        ->expectsTable(['Service', 'Status', 'Since', 'Reason'], [
            ['anthropic', 'ok', '', ''],
            ['google', 'ok', '', ''],
            ['messagebird', 'ok', '', ''],
            ['mollie', 'ok', '', ''],
            ['openai', 'failing', '2026-10-29 09:00:00', 'Incorrect API key provided: [redacted]'],
        ])
        ->assertFailed();
});

it('prints the status as JSON', function () {
    $this->travelTo('2026-10-29 09:00:00');
    ApiKeyAlert::failed('mollie', 'Unauthorized');

    // The JSON goes out in one write, so decode the real output instead of matching substrings.
    $this->withoutMockingConsoleOutput();

    expect($this->artisan('api-key-alert:status', ['--json' => true]))->toBe(1);

    $status = json_decode(Artisan::output(), true);

    expect($status['mollie'])->toBe(['status' => 'failing', 'since' => '2026-10-29 09:00:00', 'reason' => 'Unauthorized'])
        ->and($status['openai'])->toBe(['status' => 'ok', 'since' => null, 'reason' => null]);
});

it('describes a failure and forgets it on recovery', function () {
    expect(ApiKeyAlert::failure('google'))->toBeNull();

    ApiKeyAlert::failed('google', 'REQUEST_DENIED');

    expect(ApiKeyAlert::failure('google'))->toBeInstanceOf(Failure::class)
        ->and(ApiKeyAlert::failure('google')?->reason)->toBe('REQUEST_DENIED')
        ->and(ApiKeyAlert::failure('google')?->since)->toBe(now()->toDateTimeString());

    ApiKeyAlert::recovered('google');

    expect(ApiKeyAlert::failure('google'))->toBeNull();
});

it('sends a test alert and a test recovery mail without touching the cache or the events', function () {
    Event::fake([ApiKeyFailed::class, ApiKeyRecovered::class]);

    $this->artisan('api-key-alert:test')
        ->expectsOutputToContain('Sent a test alert and a test recovery mail for "test" to alerts@example.test.')
        ->assertSuccessful();

    Mail::assertSent(ApiKeyFailedMail::class, fn (ApiKeyFailedMail $mail) => $mail->service === 'test'
        && $mail->context['source'] === 'api-key-alert:test'
        && $mail->hasTo('alerts@example.test'));
    Mail::assertSent(ApiKeyRecoveredMail::class, fn (ApiKeyRecoveredMail $mail) => $mail->service === 'test');

    Event::assertNothingDispatched();
    expect(Cache::has('api-key-alert:down:test'))->toBeFalse()
        ->and(Cache::has('api-key-alert:alerted:test'))->toBeFalse();
});

it('uses the given service name in the test mails', function () {
    $this->artisan('api-key-alert:test', ['service' => 'google'])->assertSuccessful();

    Mail::assertSent(ApiKeyFailedMail::class, fn (ApiKeyFailedMail $mail) => $mail->service === 'google');
});

it('refuses to send a test without recipients', function () {
    config(['api-key-alert.mail.to' => null]);

    $this->artisan('api-key-alert:test')
        ->expectsOutputToContain('No recipients')
        ->assertFailed();

    Mail::assertNothingSent();
});

it('reports a mailer failure instead of throwing', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Connection refused'));
    Log::shouldReceive('error')->once()->withArgs(fn (string $message) => str_contains($message, 'Connection refused'));

    $this->artisan('api-key-alert:test')
        ->expectsOutputToContain('The mailer failed')
        ->assertFailed();
});
