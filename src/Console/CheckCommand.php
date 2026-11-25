<?php

namespace Darvis\LaravelApiKeyAlert\Console;

use Darvis\LaravelApiKeyAlert\ApiKeyAlertManager;
use Darvis\LaravelApiKeyAlert\Support\ApiKeyAlertConfig;
use Darvis\LaravelApiKeyAlert\Support\CheckStatus;
use Illuminate\Console\Command;

class CheckCommand extends Command
{
    protected $signature = 'api-key-alert:check {service?* : Only check these services (or "environment", "missing-keys")}';

    protected $description = 'Check whether the configured API keys are still accepted, the required keys are set and the environment settings are right';

    public function handle(ApiKeyAlertManager $alert): int
    {
        /** @var array<int, string> $requested */
        $requested = (array) $this->argument('service');
        $services = $requested !== [] ? $requested : ApiKeyAlertConfig::services();

        // The environment check runs with the full run when it is enabled, or when asked for by name.
        if ($requested === [] && ApiKeyAlertConfig::environmentCheckEnabled()) {
            $services[] = ApiKeyAlertManager::ENVIRONMENT;
        }

        // The same for the required keys check.
        if ($requested === [] && ApiKeyAlertConfig::requiredKeysCheckEnabled()) {
            $services[] = ApiKeyAlertManager::MISSING_KEYS;
        }

        $rows = [];
        $failed = false;

        foreach ($services as $service) {
            $result = match ($service) {
                ApiKeyAlertManager::ENVIRONMENT => $alert->checkEnvironment(),
                ApiKeyAlertManager::MISSING_KEYS => $alert->checkRequiredKeys(),
                default => $alert->check($service),
            };
            $failed = $failed || $result->status === CheckStatus::Failed;

            $rows[] = [$service, $result->status->value, $result->detail ?? ''];
        }

        $this->table(['Service', 'Status', 'Detail'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
