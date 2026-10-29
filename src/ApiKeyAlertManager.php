<?php

namespace Darvis\LaravelApiKeyAlert;

use Darvis\LaravelApiKeyAlert\Events\ApiKeyFailed;
use Darvis\LaravelApiKeyAlert\Events\ApiKeyRecovered;
use Darvis\LaravelApiKeyAlert\Mail\ApiKeyFailedMail;
use Darvis\LaravelApiKeyAlert\Mail\ApiKeyRecoveredMail;
use Darvis\LaravelApiKeyAlert\Support\ApiKeyAlertConfig;
use Darvis\LaravelApiKeyAlert\Support\CheckResult;
use Darvis\LaravelApiKeyAlert\Support\CheckStatus;
use Darvis\LaravelApiKeyAlert\Support\Redactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\RequestException;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Decides whether a key failed or recovered, and sends the alert.
 *
 * Every entry point runs inside someone else's request, so nothing here throws: a broken
 * alert must never break the page that happened to call the API.
 */
class ApiKeyAlertManager
{
    /**
     * Set while an alert is handled or sent. Sending the mail can itself call an API (a mail
     * driver over HTTP); without this flag a dead key of that API would alert about itself
     * in a loop.
     */
    private bool $busy = false;

    /**
     * Set while the daily check sends its request, so the Http listener doesn't inspect the
     * same response a second time.
     */
    private bool $httpMuted = false;

    /**
     * Report a rejected key by hand.
     *
     * @param  array<string, mixed>  $context
     */
    public function failed(string $service, string $reason, array $context = []): void
    {
        if (! ApiKeyAlertConfig::enabled() || $this->busy) {
            return;
        }

        $this->busy = true;

        try {
            $reason = Redactor::text($reason);
            $context = Redactor::context($context);

            Cache::add($this->downKey($service), [
                'reason' => $reason,
                'since' => now()->toDateTimeString(),
            ], now()->addDays(30));

            // Cache::add is atomic, so of two requests failing at once only one sends the mail.
            $alerted = Cache::add($this->throttleKey($service), true, now()->addMinutes(ApiKeyAlertConfig::throttleMinutes()));

            event(new ApiKeyFailed($service, $reason, $context, $alerted));

            if ($alerted) {
                Log::warning("API key alert: {$service} rejected the API key", ['reason' => $reason] + $context);

                $this->deliver(new ApiKeyFailedMail($service, $reason, $context + $this->environment(), ApiKeyAlertConfig::throttleMinutes()));
            }
        } catch (Throwable $e) {
            $this->logOwnError($e);
        } finally {
            $this->busy = false;
        }
    }

    /**
     * Report that a key works. Sends the recovery mail only when the key was reported as failed.
     */
    public function recovered(string $service): void
    {
        if (! ApiKeyAlertConfig::enabled() || $this->busy) {
            return;
        }

        $this->busy = true;

        try {
            $down = Cache::get($this->downKey($service));

            // forget() tells whether it removed the entry, so only one request sends the mail.
            if (! is_array($down) || ! Cache::forget($this->downKey($service))) {
                return;
            }

            Cache::forget($this->throttleKey($service));

            $since = is_string($down['since'] ?? null) ? $down['since'] : null;

            event(new ApiKeyRecovered($service, $since));

            Log::info("API key alert: {$service} accepts the API key again");

            $this->deliver(new ApiKeyRecoveredMail($service, $since, $this->environment()));
        } catch (Throwable $e) {
            $this->logOwnError($e);
        } finally {
            $this->busy = false;
        }
    }

    /**
     * Whether the service is currently reported as failed.
     */
    public function isFailing(string $service): bool
    {
        return Cache::has($this->downKey($service));
    }

    /**
     * Run a call to the service. A rejected key is reported, then the exception is thrown again:
     * the guard never swallows an exception. A call that returns marks the key as working.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @param  bool  $reportAll  Report every exception, not only the ones recognised as a rejected key.
     * @return TReturn
     *
     * @throws Throwable
     */
    public function guard(string $service, callable $callback, bool $reportAll = false): mixed
    {
        try {
            $result = $callback();
        } catch (Throwable $e) {
            $reason = $this->classify($e, $service)['reason'] ?? null;

            if ($reason === null && $reportAll) {
                $reason = $this->messageOf($e);
            }

            if ($reason !== null) {
                $this->failed($service, $reason, ['exception' => $e::class]);
            }

            throw $e;
        }

        $this->recovered($service);

        return $result;
    }

    /**
     * Inspect a response of the service, for code that talks to the API without the Http client.
     * Returns false when the response shows a rejected key.
     */
    public function inspectResponse(string $service, int $status, string $body, ?string $url = null): bool
    {
        $reason = ApiKeyAlertConfig::detectorFor($service)->detect($status, $body);

        if ($reason !== null) {
            $this->failed($service, $reason, array_filter(['url' => $url, 'status' => $status]));

            return false;
        }

        if ($status >= 200 && $status < 300) {
            $this->recovered($service);
        }

        return true;
    }

    /**
     * Send the daily check request of one service.
     */
    public function check(string $service): CheckResult
    {
        $check = ApiKeyAlertConfig::check($service);

        if ($check === null) {
            return new CheckResult($service, CheckStatus::Skipped);
        }

        $request = Http::timeout(15)->withHeaders($check['headers'])->withQueryParameters($check['query']);

        if ($check['token'] !== null) {
            $request = $request->withToken($check['token']);
        }

        $this->httpMuted = true;

        try {
            $response = $request->send($check['method'], $check['url']);
        } catch (ConnectionException $e) {
            return new CheckResult($service, CheckStatus::Inconclusive, Redactor::text($e->getMessage()));
        } finally {
            $this->httpMuted = false;
        }

        $reason = ApiKeyAlertConfig::detectorFor($service)->detect($response->status(), $response->body());

        if ($reason !== null) {
            $this->failed($service, $reason, ['url' => $check['url'], 'status' => $response->status(), 'source' => 'daily check']);

            return new CheckResult($service, CheckStatus::Failed, Redactor::text($reason));
        }

        if ($response->successful()) {
            $this->recovered($service);

            return new CheckResult($service, CheckStatus::Ok);
        }

        return new CheckResult($service, CheckStatus::Inconclusive, "HTTP {$response->status()}");
    }

    /**
     * Listener for every response of the Laravel Http client.
     *
     * @internal
     */
    public function handleResponse(ResponseReceived $event): void
    {
        if ($this->httpMuted || $this->busy || ! ApiKeyAlertConfig::detectHttp()) {
            return;
        }

        try {
            $url = $event->request->url();
            $service = ApiKeyAlertConfig::serviceForHost((string) parse_url($url, PHP_URL_HOST));

            if ($service !== null) {
                $this->inspectResponse($service, $event->response->status(), $event->response->body(), $url);
            }
        } catch (Throwable $e) {
            $this->logOwnError($e);
        }
    }

    /**
     * Callback for every exception the application reports.
     *
     * @internal
     */
    public function handleReportedException(Throwable $exception): void
    {
        if ($this->busy || ! ApiKeyAlertConfig::detectExceptions()) {
            return;
        }

        try {
            $failure = $this->classify($exception);

            if ($failure !== null) {
                $this->failed($failure['service'], $failure['reason'], ['exception' => $exception::class]);
            }
        } catch (Throwable $e) {
            $this->logOwnError($e);
        }
    }

    /**
     * Recognise a rejected key in an exception: the HTTP response it carries, or a class listed
     * under a service's "exceptions".
     *
     * @return array{service: string, reason: string}|null
     */
    protected function classify(Throwable $exception, ?string $service = null): ?array
    {
        [$status, $body, $host] = $this->responseOf($exception);

        if ($status !== null) {
            $service ??= ApiKeyAlertConfig::serviceForHost($host);
            $reason = $service !== null ? ApiKeyAlertConfig::detectorFor($service)->detect($status, $body) : null;

            if ($service !== null && $reason !== null) {
                return ['service' => $service, 'reason' => $reason];
            }
        }

        $listedFor = ApiKeyAlertConfig::serviceForException($exception);

        if ($listedFor !== null && ($service === null || $service === $listedFor)) {
            return ['service' => $listedFor, 'reason' => $this->messageOf($exception)];
        }

        return null;
    }

    /**
     * The status, body and host of the HTTP response an exception carries, if any.
     *
     * @return array{0: int|null, 1: string, 2: string}
     */
    private function responseOf(Throwable $exception): array
    {
        if ($exception instanceof RequestException) {
            $response = $exception->response;

            return [$response->status(), $response->body(), (string) $response->effectiveUri()?->getHost()];
        }

        // Guzzle and the SDKs built on it (Mollie, for one) attach the PSR-7 request and response.
        $response = method_exists($exception, 'getResponse') ? $exception->getResponse() : null;

        if (! $response instanceof ResponseInterface) {
            return [null, '', ''];
        }

        $request = method_exists($exception, 'getRequest') ? $exception->getRequest() : null;
        $host = $request instanceof RequestInterface ? $request->getUri()->getHost() : '';

        return [$response->getStatusCode(), (string) $response->getBody(), $host];
    }

    private function messageOf(Throwable $exception): string
    {
        // Mollie's getMessage() appends the request body; getPlainMessage() is only the error.
        $message = method_exists($exception, 'getPlainMessage') ? (string) $exception->getPlainMessage() : $exception->getMessage();

        return mb_strimwidth(trim($message) !== '' ? trim($message) : $exception::class, 0, 500, '…');
    }

    /**
     * Send the mail without slowing down the request that found the failure: through the
     * queue when configured, else after the response is sent, or right away in the console.
     */
    private function deliver(Mailable $mail): void
    {
        $recipients = ApiKeyAlertConfig::mailTo();

        if ($recipients === []) {
            Log::warning('API key alert: no recipients, set API_KEY_ALERT_MAIL_TO to receive alerts by mail');

            return;
        }

        if (ApiKeyAlertConfig::queueMail()) {
            Mail::to($recipients)->queue($mail);

            return;
        }

        if (app()->runningInConsole()) {
            $this->send($recipients, $mail);

            return;
        }

        app()->terminating(fn () => $this->send($recipients, $mail));
    }

    /**
     * @param  array<int, string>  $recipients
     */
    private function send(array $recipients, Mailable $mail): void
    {
        $wasBusy = $this->busy;
        $this->busy = true;

        try {
            Mail::to($recipients)->send($mail);
        } catch (Throwable $e) {
            $this->logOwnError($e);
        } finally {
            $this->busy = $wasBusy;
        }
    }

    /**
     * @return array<string, string>
     */
    private function environment(): array
    {
        return array_filter([
            'app' => (string) config('app.name'),
            'environment' => (string) app()->environment(),
            'app_url' => (string) config('app.url'),
            'server' => (string) gethostname(),
            'time' => now()->toDateTimeString(),
        ]);
    }

    private function logOwnError(Throwable $e): void
    {
        Log::error('API key alert: '.Redactor::text($e->getMessage()), ['exception' => $e::class]);
    }

    private function downKey(string $service): string
    {
        return 'api-key-alert:down:'.$service;
    }

    private function throttleKey(string $service): string
    {
        return 'api-key-alert:alerted:'.$service;
    }
}
