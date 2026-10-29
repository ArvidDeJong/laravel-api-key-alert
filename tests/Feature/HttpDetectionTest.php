<?php

use Darvis\LaravelApiKeyAlert\Events\ApiKeyFailed;
use Darvis\LaravelApiKeyAlert\Mail\ApiKeyFailedMail;
use Darvis\LaravelApiKeyAlert\Mail\ApiKeyRecoveredMail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['api-key-alert.mail.to' => 'alerts@example.test']);
    Mail::fake();
});

it('alerts once when Google answers HTTP 200 with REQUEST_DENIED', function () {
    Http::fake(['maps.googleapis.com/*' => Http::response($this->googleRequestDenied())]);

    Http::get('https://maps.googleapis.com/maps/api/geocode/json', ['address' => 'Amsterdam', 'key' => 'AIzaSyA1234567890abcdefghijklmnopqrstuv']);
    Http::get('https://maps.googleapis.com/maps/api/directions/json', ['key' => 'AIzaSyA1234567890abcdefghijklmnopqrstuv']);

    Mail::assertSent(ApiKeyFailedMail::class, 1);
    Mail::assertSent(ApiKeyFailedMail::class, function (ApiKeyFailedMail $mail) {
        return $mail->hasTo('alerts@example.test')
            && $mail->service === 'google'
            && str_contains($mail->reason, '2a01:7c8:bb0d:22e:5054:ff:fee6:b80a')
            && str_contains($mail->context['url'], 'key=[redacted]')
            && ! str_contains(json_encode($mail->context), 'AIzaSy');
    });
});

it('ignores a normal Google response', function () {
    Http::fake(['maps.googleapis.com/*' => Http::response(['results' => [], 'status' => 'ZERO_RESULTS'])]);

    Http::get('https://maps.googleapis.com/maps/api/geocode/json');

    Mail::assertNothingSent();
});

it('alerts on HTTP 401 with the message from the error body', function () {
    Http::fake(['api.openai.com/*' => Http::response([
        'error' => ['message' => 'Incorrect API key provided.', 'code' => 'invalid_api_key'],
    ], 401)]);

    Http::get('https://api.openai.com/v1/models');

    Mail::assertSent(ApiKeyFailedMail::class, fn (ApiKeyFailedMail $mail) => $mail->service === 'openai'
        && $mail->reason === 'Incorrect API key provided.');
});

it('ignores hosts that belong to no service', function () {
    Http::fake(['example.test/*' => Http::response('Unauthorized', 401)]);

    Http::get('https://example.test/api');

    Mail::assertNothingSent();
});

it('sends one recovery mail when the key works again', function () {
    Http::fakeSequence('api.openai.com/*')
        ->push(['error' => ['message' => 'Incorrect API key provided.']], 401)
        ->push(['data' => []])
        ->push(['data' => []]);

    Http::get('https://api.openai.com/v1/models');
    Http::get('https://api.openai.com/v1/models');
    Http::get('https://api.openai.com/v1/models');

    Mail::assertSent(ApiKeyFailedMail::class, 1);
    Mail::assertSent(ApiKeyRecoveredMail::class, 1);
});

it('alerts again straight away when a recovered key breaks again', function () {
    Http::fakeSequence('api.openai.com/*')
        ->push([], 401)
        ->push(['data' => []])
        ->push([], 401);

    Http::get('https://api.openai.com/v1/models');
    Http::get('https://api.openai.com/v1/models');
    Http::get('https://api.openai.com/v1/models');

    Mail::assertSent(ApiKeyFailedMail::class, 2);
});

it('does nothing when Http detection is off', function () {
    config(['api-key-alert.detect.http' => false]);
    Http::fake(['api.openai.com/*' => Http::response([], 401)]);

    Http::get('https://api.openai.com/v1/models');

    Mail::assertNothingSent();
});

it('still fires the event when no recipient is configured', function () {
    config(['api-key-alert.mail.to' => null]);
    Event::fake([ApiKeyFailed::class]);
    Http::fake(['api.openai.com/*' => Http::response([], 401)]);

    Http::get('https://api.openai.com/v1/models');

    Mail::assertNothingSent();
    Event::assertDispatched(ApiKeyFailed::class, fn (ApiKeyFailed $event) => $event->service === 'openai'
        && $event->reason === 'HTTP 401');
});

it('fires the event on every failure but mails once per window', function () {
    Event::fake([ApiKeyFailed::class]);
    Http::fake(['api.openai.com/*' => Http::response([], 401)]);

    Http::get('https://api.openai.com/v1/models');
    Http::get('https://api.openai.com/v1/models');

    Event::assertDispatchedTimes(ApiKeyFailed::class, 2);
    Mail::assertSent(ApiKeyFailedMail::class, 1);
});

it('matches a wildcard host on any subdomain', function () {
    Http::fake(['translation.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid.', 'status' => 'INVALID_ARGUMENT']], 403)]);

    Http::post('https://translation.googleapis.com/language/translate/v2');

    Mail::assertSent(ApiKeyFailedMail::class, fn (ApiKeyFailedMail $mail) => $mail->service === 'google');
});
