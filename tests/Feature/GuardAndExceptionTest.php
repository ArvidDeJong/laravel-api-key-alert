<?php

use Darvis\LaravelApiKeyAlert\Facades\ApiKeyAlert;
use Darvis\LaravelApiKeyAlert\Mail\ApiKeyFailedMail;
use Darvis\LaravelApiKeyAlert\Mail\ApiKeyRecoveredMail;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class FakeSdkAuthenticationException extends RuntimeException {}

beforeEach(function () {
    config(['api-key-alert.mail.to' => 'alerts@example.test']);
    Mail::fake();
});

function mollieUnauthorized(): ClientException
{
    return new ClientException(
        'Client error: 401 Unauthorized',
        new PsrRequest('GET', 'https://api.mollie.com/v2/payments/tr_123'),
        new PsrResponse(401, [], json_encode(['status' => 401, 'title' => 'Unauthorized Request', 'detail' => 'Missing authentication, or failed to authenticate'])),
    );
}

it('reports a rejected key from a guarded call and throws the exception again', function () {
    config(['api-key-alert.detect.http' => false]);
    Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key provided.']], 401)]);

    expect(fn () => ApiKeyAlert::guard('openai', fn () => Http::get('https://api.openai.com/v1/models')->throw()))
        ->toThrow(RequestException::class);

    Mail::assertSent(ApiKeyFailedMail::class, fn (ApiKeyFailedMail $mail) => $mail->service === 'openai'
        && $mail->reason === 'Incorrect API key provided.');
});

it('throws an unrelated exception from a guarded call without an alert', function () {
    expect(fn () => ApiKeyAlert::guard('mollie', fn () => throw new RuntimeException('Timeout')))
        ->toThrow(RuntimeException::class, 'Timeout');

    Mail::assertNothingSent();
});

it('reports every exception of a guarded call when asked to', function () {
    expect(fn () => ApiKeyAlert::guard('mollie', fn () => throw new RuntimeException('Timeout'), reportAll: true))
        ->toThrow(RuntimeException::class);

    Mail::assertSent(ApiKeyFailedMail::class, fn (ApiKeyFailedMail $mail) => $mail->reason === 'Timeout');
});

it('returns the result of a guarded call and marks the key as working', function () {
    ApiKeyAlert::failed('mollie', 'Unauthorized');

    expect(ApiKeyAlert::guard('mollie', fn () => 'paid'))->toBe('paid')
        ->and(ApiKeyAlert::isFailing('mollie'))->toBeFalse();

    Mail::assertSent(ApiKeyRecoveredMail::class, 1);
});

it('recognises a reported exception that carries the PSR-7 response', function () {
    report(mollieUnauthorized());

    Mail::assertSent(ApiKeyFailedMail::class, fn (ApiKeyFailedMail $mail) => $mail->service === 'mollie'
        && $mail->reason === 'Missing authentication, or failed to authenticate');
});

it('recognises a reported exception listed under a service', function () {
    config(['api-key-alert.services.messagebird.exceptions' => [FakeSdkAuthenticationException::class]]);

    report(new FakeSdkAuthenticationException('Got error response from the server: Request not allowed (incorrect access_key)'));

    Mail::assertSent(ApiKeyFailedMail::class, fn (ApiKeyFailedMail $mail) => $mail->service === 'messagebird');
});

it('ignores reported exceptions that are not about a key', function () {
    report(new RuntimeException('Something else broke'));

    Mail::assertNothingSent();
});

it('ignores reported exceptions when exception detection is off', function () {
    config(['api-key-alert.detect.exceptions' => false]);

    report(mollieUnauthorized());

    Mail::assertNothingSent();
});
