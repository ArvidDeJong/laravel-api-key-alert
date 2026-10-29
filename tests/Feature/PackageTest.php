<?php

use Darvis\LaravelApiKeyAlert\ApiKeyAlertManager;
use Darvis\LaravelApiKeyAlert\ApiKeyAlertServiceProvider;
use Darvis\LaravelApiKeyAlert\Detectors\GoogleDetector;
use Darvis\LaravelApiKeyAlert\Detectors\StatusDetector;
use Darvis\LaravelApiKeyAlert\Support\ApiKeyAlertConfig;
use Illuminate\Support\ServiceProvider;

function apiKeyAlertRoot(string $path = ''): string
{
    return dirname(__DIR__, 2).($path === '' ? '' : '/'.$path);
}

it('registers the manager as a singleton', function () {
    expect(app(ApiKeyAlertManager::class))->toBe(app(ApiKeyAlertManager::class));
});

it('offers the config, views and translations for publishing', function (string $tag, string $source) {
    // Never run vendor:publish here: it writes into the Testbench app.
    $paths = ServiceProvider::pathsToPublish(ApiKeyAlertServiceProvider::class, $tag);

    expect($paths)->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith($source);
})->with([
    ['api-key-alert-config', 'config/api-key-alert.php'],
    ['api-key-alert-views', 'resources/views'],
    ['api-key-alert-translations', 'resources/lang'],
]);

it('reads the shipped defaults', function () {
    expect(ApiKeyAlertConfig::enabled())->toBeTrue()
        ->and(ApiKeyAlertConfig::detectHttp())->toBeTrue()
        ->and(ApiKeyAlertConfig::detectExceptions())->toBeTrue()
        ->and(ApiKeyAlertConfig::checkEnabled())->toBeFalse()
        ->and(ApiKeyAlertConfig::checkTime())->toBe('07:00')
        ->and(ApiKeyAlertConfig::mailTo())->toBe([])
        ->and(ApiKeyAlertConfig::queueMail())->toBeFalse()
        ->and(ApiKeyAlertConfig::throttleMinutes())->toBe(360)
        ->and(ApiKeyAlertConfig::services())->toBe(['anthropic', 'google', 'messagebird', 'mollie', 'openai']);
});

it('picks the detector of a service', function () {
    expect(ApiKeyAlertConfig::detectorFor('google'))->toBeInstanceOf(GoogleDetector::class)
        ->and(ApiKeyAlertConfig::detectorFor('openai'))->toBeInstanceOf(StatusDetector::class)
        ->and(ApiKeyAlertConfig::detectorFor('unknown'))->toBeInstanceOf(StatusDetector::class);
});

it('skips a check whose key is empty', function () {
    expect(ApiKeyAlertConfig::check('google'))->toBeNull();

    config(['api-key-alert.services.google.check.query.key' => 'AIza-test']);

    expect(ApiKeyAlertConfig::check('google'))->toMatchArray([
        'method' => 'GET',
        'query' => ['address' => 'Amsterdam', 'key' => 'AIza-test'],
    ]);
});

it('falls back to the default check time when the value is not a time', function () {
    config(['api-key-alert.check.time' => 'tomorrow']);

    expect(ApiKeyAlertConfig::checkTime())->toBe('07:00');
});

it('ships the same translation keys in every locale', function () {
    $keys = fn (string $locale) => array_keys(Arr::dot(require apiKeyAlertRoot("resources/lang/{$locale}/mail.php")));

    expect($keys('nl'))->toBe($keys('en'));
});

it('lets nothing outside the accessor read the package config', function () {
    $offenders = [];

    foreach (['src', 'resources'] as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(apiKeyAlertRoot($directory))) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(apiKeyAlertRoot().'/', '', $file->getPathname());

            // The accessor is where the reading happens, and the Boost files quote the call they forbid.
            if (str_ends_with($relative, 'ApiKeyAlertConfig.php') || str_starts_with($relative, 'resources/boost/')) {
                continue;
            }

            if (preg_match("/(config\\(|Config::get\\()['\"]api-key-alert\\./", (string) file_get_contents($file->getPathname()))) {
                $offenders[] = $relative;
            }
        }
    }

    expect($offenders)->toBe([], 'these read the config directly instead of through ApiKeyAlertConfig');
});

it('keeps the config keys in alphabetical order, at every level', function () {
    $walk = function (array $config, string $trail) use (&$walk): void {
        $keys = array_keys($config);

        if ($keys !== array_filter($keys, 'is_string')) {
            return;
        }

        $sorted = $keys;
        sort($sorted);

        expect($keys)->toBe($sorted, "the keys in '{$trail}' are not in alphabetical order");

        foreach ($config as $key => $value) {
            if (is_array($value) && $value !== []) {
                $walk($value, $trail.'.'.$key);
            }
        }
    };

    $walk(require apiKeyAlertRoot('config/api-key-alert.php'), 'api-key-alert');
});
