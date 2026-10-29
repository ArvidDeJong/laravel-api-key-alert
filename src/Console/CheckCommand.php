<?php

namespace Darvis\LaravelApiKeyAlert\Console;

use Darvis\LaravelApiKeyAlert\ApiKeyAlertManager;
use Darvis\LaravelApiKeyAlert\Support\ApiKeyAlertConfig;
use Darvis\LaravelApiKeyAlert\Support\CheckStatus;
use Illuminate\Console\Command;

class CheckCommand extends Command
{
    protected $signature = 'api-key-alert:check {service?* : Only check these services}';

    protected $description = 'Check whether the configured API keys are still accepted';

    public function handle(ApiKeyAlertManager $alert): int
    {
        /** @var array<int, string> $requested */
        $requested = (array) $this->argument('service');
        $services = $requested !== [] ? $requested : ApiKeyAlertConfig::services();

        $rows = [];
        $failed = false;

        foreach ($services as $service) {
            $result = $alert->check($service);
            $failed = $failed || $result->status === CheckStatus::Failed;

            $rows[] = [$service, $result->status->value, $result->detail ?? ''];
        }

        $this->table(['Service', 'Status', 'Detail'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
