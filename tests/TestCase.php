<?php

namespace Darvis\LaravelApiKeyAlert\Tests;

use Darvis\LaravelApiKeyAlert\ApiKeyAlertServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // A forgotten fake fails the test instead of calling a real API.
        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ApiKeyAlertServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.name', 'Test App');
        $app['config']->set('cache.default', 'array');
    }

    /**
     * The body Google Maps returns, with HTTP 200, for a key that is not allowed from this server.
     *
     * @return array<string, mixed>
     */
    protected function googleRequestDenied(): array
    {
        return [
            'error_message' => 'This IP, site or mobile application is not authorized to use this API key. Request received from IP address 2a01:7c8:bb0d:22e:5054:ff:fee6:b80a, with empty referer',
            'results' => [],
            'status' => 'REQUEST_DENIED',
        ];
    }
}
