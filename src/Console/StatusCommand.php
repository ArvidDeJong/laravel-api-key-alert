<?php

namespace Darvis\LaravelApiKeyAlert\Console;

use Darvis\LaravelApiKeyAlert\ApiKeyAlertManager;
use Darvis\LaravelApiKeyAlert\Support\ApiKeyAlertConfig;
use Illuminate\Console\Command;

class StatusCommand extends Command
{
    protected $signature = 'api-key-alert:status {--json : Print the status as JSON}';

    protected $description = 'Show which API keys are currently reported as rejected, and whether the environment settings are right';

    public function handle(ApiKeyAlertManager $alert): int
    {
        $names = ApiKeyAlertConfig::services();

        if (ApiKeyAlertConfig::environmentCheckEnabled()) {
            $names[] = ApiKeyAlertManager::ENVIRONMENT;
        }

        $status = [];

        foreach ($names as $service) {
            $failure = $alert->failure($service);

            $status[$service] = [
                'status' => $failure === null ? 'ok' : 'failing',
                'since' => $failure?->since,
                'reason' => $failure?->reason,
            ];
        }

        $failing = array_filter($status, fn (array $row): bool => $row['status'] === 'failing');

        if ($this->option('json')) {
            $this->line((string) json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Service', 'Status', 'Since', 'Reason'], array_map(
                fn (string $service, array $row): array => [$service, $row['status'], $row['since'] ?? '', $row['reason'] ?? ''],
                array_keys($status),
                $status,
            ));
        }

        return $failing === [] ? self::SUCCESS : self::FAILURE;
    }
}
