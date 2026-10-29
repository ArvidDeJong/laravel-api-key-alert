<?php

namespace Darvis\LaravelApiKeyAlert\Console;

use Darvis\LaravelApiKeyAlert\ApiKeyAlertManager;
use Darvis\LaravelApiKeyAlert\Support\ApiKeyAlertConfig;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'api-key-alert:test {service=test : The service name shown in the mails}';

    protected $description = 'Send a test alert and a test recovery mail to the configured recipients';

    public function handle(ApiKeyAlertManager $alert): int
    {
        $recipients = ApiKeyAlertConfig::mailTo();

        if ($recipients === []) {
            $this->error('No recipients. Set API_KEY_ALERT_MAIL_TO to one address or a comma separated list.');

            return self::FAILURE;
        }

        /** @var string $service */
        $service = $this->argument('service');

        if (! $alert->test($service)) {
            $this->error('The mailer failed. The reason is in the log, under "API key alert".');

            return self::FAILURE;
        }

        $this->info(sprintf('Sent a test alert and a test recovery mail for "%s" to %s.', $service, implode(', ', $recipients)));

        return self::SUCCESS;
    }
}
