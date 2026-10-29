<?php

namespace Darvis\LaravelApiKeyAlert\Facades;

use Darvis\LaravelApiKeyAlert\ApiKeyAlertManager;
use Darvis\LaravelApiKeyAlert\Support\CheckResult;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void failed(string $service, string $reason, array<string, mixed> $context = [])
 * @method static void recovered(string $service)
 * @method static bool isFailing(string $service)
 * @method static mixed guard(string $service, callable $callback, bool $reportAll = false)
 * @method static bool inspectResponse(string $service, int $status, string $body, ?string $url = null)
 * @method static CheckResult check(string $service)
 *
 * @see ApiKeyAlertManager
 */
class ApiKeyAlert extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ApiKeyAlertManager::class;
    }
}
