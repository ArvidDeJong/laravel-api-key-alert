<?php

namespace Darvis\LaravelApiKeyAlert;

use Darvis\LaravelApiKeyAlert\Console\CheckCommand;
use Darvis\LaravelApiKeyAlert\Support\ApiKeyAlertConfig;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Throwable;

class ApiKeyAlertServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/api-key-alert.php', 'api-key-alert');

        $this->app->singleton(ApiKeyAlertManager::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'api-key-alert');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'api-key-alert');

        // The listener and the report callback check their switch on every call, so a config
        // change in a test or at runtime applies without booting again.
        Event::listen(ResponseReceived::class, [ApiKeyAlertManager::class, 'handleResponse']);

        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            // reportable() is on the framework's Handler, not on the contract.
            if (method_exists($handler, 'reportable')) {
                $handler->reportable(function (Throwable $e): void {
                    $this->app->make(ApiKeyAlertManager::class)->handleReportedException($e);
                });
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([CheckCommand::class]);

            $this->publishes([
                __DIR__.'/../config/api-key-alert.php' => config_path('api-key-alert.php'),
            ], 'api-key-alert-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/api-key-alert'),
            ], 'api-key-alert-views');

            $this->publishes([
                __DIR__.'/../resources/lang' => $this->app->langPath('vendor/api-key-alert'),
            ], 'api-key-alert-translations');
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (ApiKeyAlertConfig::checkEnabled()) {
                $schedule->command(CheckCommand::class)->dailyAt(ApiKeyAlertConfig::checkTime());
            }
        });
    }
}
