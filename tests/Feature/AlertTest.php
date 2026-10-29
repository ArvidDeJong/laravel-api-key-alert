<?php

use Darvis\LaravelApiKeyAlert\Facades\ApiKeyAlert;
use Darvis\LaravelApiKeyAlert\Mail\ApiKeyFailedMail;
use Darvis\LaravelApiKeyAlert\Mail\ApiKeyRecoveredMail;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['api-key-alert.mail.to' => 'alerts@example.test']);
});

it('sends a manual alert to every configured address', function () {
    config(['api-key-alert.mail.to' => 'one@example.test, two@example.test, not-an-address']);
    Mail::fake();

    ApiKeyAlert::failed('mollie', 'Unauthorized');

    Mail::assertSent(ApiKeyFailedMail::class, fn (ApiKeyFailedMail $mail) => $mail->hasTo('one@example.test')
        && $mail->hasTo('two@example.test')
        && count($mail->to) === 2);
});

it('strips keys from the reason and the context', function () {
    Mail::fake();

    ApiKeyAlert::failed('google', 'Key AIzaSyA1234567890abcdefghijklmnopqrstuv was rejected', [
        'url' => 'https://maps.googleapis.com/maps/api/geocode/json?address=Amsterdam&key=secret-value',
        'header' => 'Bearer sk-abcdefghijklmnopqrstuvwxyz',
    ]);

    Mail::assertSent(ApiKeyFailedMail::class, function (ApiKeyFailedMail $mail) {
        $text = $mail->reason.json_encode($mail->context);

        return ! str_contains($text, 'AIzaSy')
            && ! str_contains($text, 'secret-value')
            && ! str_contains($text, 'sk-abc')
            && str_contains($mail->context['url'], 'address=Amsterdam');
    });
});

it('renders the mails in English and Dutch', function (string $locale, string $failedHeading, string $recoveredHeading) {
    app()->setLocale($locale);

    $failed = (new ApiKeyFailedMail('google', 'REQUEST_DENIED', ['app' => 'Test App', 'status' => 200]))->render();
    $recovered = (new ApiKeyRecoveredMail('google', '2026-10-18 20:30:04', ['app' => 'Test App']))->render();

    expect($failed)->toContain($failedHeading)->toContain('REQUEST_DENIED')
        ->and($recovered)->toContain($recoveredHeading)->toContain('2026-10-18 20:30:04');
})->with([
    ['en', 'The google API key no longer works', 'The google API key works again'],
    ['nl', 'De API-sleutel van google werkt niet meer', 'De API-sleutel van google werkt weer'],
]);

it('sends the mail after the response in a web request', function () {
    Mail::fake();
    (fn () => $this->isRunningInConsole = false)->call(app());

    ApiKeyAlert::failed('mollie', 'Unauthorized');

    Mail::assertNothingSent();

    app()->terminate();

    Mail::assertSent(ApiKeyFailedMail::class, 1);
});

it('queues the mail when configured', function () {
    config(['api-key-alert.mail.queue' => true]);
    Mail::fake();

    ApiKeyAlert::failed('mollie', 'Unauthorized');

    Mail::assertQueued(ApiKeyFailedMail::class);
});

it('does nothing when the package is disabled', function () {
    config(['api-key-alert.enabled' => false]);
    Mail::fake();

    ApiKeyAlert::failed('mollie', 'Unauthorized');

    Mail::assertNothingSent();
});

it('does not alert about itself while sending an alert', function () {
    // A mail driver that talks to an API over HTTP, with a dead key of its own.
    config(['mail.default' => 'array']);
    Http::fake(['maps.googleapis.com/*' => Http::response($this->googleRequestDenied())]);
    Event::listen(MessageSending::class, fn () => Http::get('https://maps.googleapis.com/maps/api/geocode/json'));

    ApiKeyAlert::failed('mollie', 'Unauthorized');

    $subjects = app('mailer')->getSymfonyTransport()->messages()
        ->map(fn ($message) => $message->getOriginalMessage()->getSubject())
        ->all();

    expect($subjects)->toBe(['[Test App] The mollie API key no longer works'])
        ->and(ApiKeyAlert::isFailing('google'))->toBeFalse();
});

it('never throws when the mail cannot be sent', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

    ApiKeyAlert::failed('mollie', 'Unauthorized');

    expect(ApiKeyAlert::isFailing('mollie'))->toBeTrue();
});
