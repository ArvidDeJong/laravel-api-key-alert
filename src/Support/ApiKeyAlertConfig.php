<?php

namespace Darvis\LaravelApiKeyAlert\Support;

use Darvis\LaravelApiKeyAlert\Detectors\Detector;
use Darvis\LaravelApiKeyAlert\Detectors\StatusDetector;
use Illuminate\Support\Str;

/**
 * The one place that reads the package config. Callers ask this class, so a default is written
 * once and a caller cannot quietly disagree with config/api-key-alert.php about what it is.
 */
final class ApiKeyAlertConfig
{
    public static function enabled(): bool
    {
        return (bool) config('api-key-alert.enabled', true);
    }

    public static function detectHttp(): bool
    {
        return self::enabled() && (bool) config('api-key-alert.detect.http', true);
    }

    public static function detectExceptions(): bool
    {
        return self::enabled() && (bool) config('api-key-alert.detect.exceptions', true);
    }

    public static function checkEnabled(): bool
    {
        return self::enabled() && (bool) config('api-key-alert.check.enabled', false);
    }

    public static function environmentCheckEnabled(): bool
    {
        return self::enabled() && (bool) config('api-key-alert.environment.check', false);
    }

    /**
     * Whether anything is scheduled: the daily check of the keys, the environment check, or both.
     */
    public static function scheduleEnabled(): bool
    {
        return self::checkEnabled() || self::environmentCheckEnabled();
    }

    /**
     * The settings the environment check compares, as config key => expected value.
     *
     * @return array<string, mixed>
     */
    public static function expectedEnvironment(): array
    {
        $expected = config('api-key-alert.environment.expected', []);

        return is_array($expected) ? array_filter($expected, 'is_string', ARRAY_FILTER_USE_KEY) : [];
    }

    /**
     * Time of day the daily check runs, as "HH:MM".
     */
    public static function checkTime(): string
    {
        $time = trim((string) config('api-key-alert.check.time', '07:00'));

        return preg_match('/^\d{1,2}:\d{2}$/', $time) === 1 ? $time : '07:00';
    }

    /**
     * Addresses that receive the alerts, without empty entries.
     *
     * @return array<int, string>
     */
    public static function mailTo(): array
    {
        $to = config('api-key-alert.mail.to');

        if (is_string($to)) {
            $to = explode(',', $to);
        }

        if (! is_array($to)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($address): string => trim((string) $address), $to),
            fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false,
        ));
    }

    public static function queueMail(): bool
    {
        return (bool) config('api-key-alert.mail.queue', false);
    }

    public static function throttleMinutes(): int
    {
        return max(1, (int) config('api-key-alert.throttle_minutes', 360));
    }

    /**
     * Names of the configured services.
     *
     * @return array<int, string>
     */
    public static function services(): array
    {
        return array_map('strval', array_keys(self::servicesConfig()));
    }

    /**
     * The service a host belongs to, or null when no service claims it.
     */
    public static function serviceForHost(string $host): ?string
    {
        $host = strtolower($host);

        if ($host === '') {
            return null;
        }

        foreach (self::servicesConfig() as $service => $config) {
            foreach ((array) ($config['hosts'] ?? []) as $pattern) {
                $pattern = strtolower((string) $pattern);

                if ($pattern === $host || (str_contains($pattern, '*') && Str::is($pattern, $host))) {
                    return (string) $service;
                }
            }
        }

        return null;
    }

    public static function detectorFor(string $service): Detector
    {
        $class = self::serviceConfig($service)['detector'] ?? null;

        if (is_string($class) && is_subclass_of($class, Detector::class)) {
            return app($class);
        }

        return app(StatusDetector::class);
    }

    /**
     * The service whose "exceptions" list contains a parent of this exception.
     */
    public static function serviceForException(\Throwable $exception): ?string
    {
        foreach (self::servicesConfig() as $service => $config) {
            foreach ((array) ($config['exceptions'] ?? []) as $class) {
                if (is_string($class) && $exception instanceof $class) {
                    return (string) $service;
                }
            }
        }

        return null;
    }

    /**
     * The request of the daily check, or null when the service has none or a value is missing.
     *
     * @return array{method: string, url: string, query: array<string, string>, headers: array<string, string>, token: string|null}|null
     */
    public static function check(string $service): ?array
    {
        $check = self::serviceConfig($service)['check'] ?? null;

        if (! is_array($check) || ! is_string($check['url'] ?? null) || $check['url'] === '') {
            return null;
        }

        $query = self::stringMap($check['query'] ?? []);
        $headers = self::stringMap($check['headers'] ?? []);
        $token = array_key_exists('token', $check) ? self::resolveValue($check['token']) : null;

        // A missing key shows up as an empty value; asking with it would only report a false alarm.
        if ($query === null || $headers === null || $token === '') {
            return null;
        }

        return [
            'method' => strtoupper((string) ($check['method'] ?? 'GET')),
            'url' => $check['url'],
            'query' => $query,
            'headers' => $headers,
            'token' => $token,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function serviceConfig(string $service): array
    {
        return self::servicesConfig()[$service] ?? [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function servicesConfig(): array
    {
        $services = config('api-key-alert.services', []);

        return is_array($services) ? array_filter($services, 'is_array') : [];
    }

    /**
     * A check value as a string. "config:services.google.key" reads that config key of the app when
     * the check runs, so a key the app already has in its config is not written down a second time.
     */
    private static function resolveValue(mixed $value): string
    {
        if (is_string($value) && str_starts_with($value, 'config:')) {
            $value = config(substr($value, strlen('config:')));
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * Cast every value to a string, or null when one of them is empty.
     *
     * @return array<string, string>|null
     */
    private static function stringMap(mixed $values): ?array
    {
        $map = [];

        foreach ((array) $values as $name => $value) {
            $value = self::resolveValue($value);

            if ($value === '') {
                return null;
            }

            $map[(string) $name] = $value;
        }

        return $map;
    }
}
