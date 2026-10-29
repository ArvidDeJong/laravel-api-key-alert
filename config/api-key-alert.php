<?php

use Darvis\LaravelApiKeyAlert\Detectors\GoogleDetector;

return [

    /*
    |--------------------------------------------------------------------------
    | Daily check
    |--------------------------------------------------------------------------
    |
    | Sends one cheap request per service that has a "check" below, so a dead
    | key is found before a visitor runs into it. Off by default: it costs a
    | few API calls a day and needs the scheduler (schedule:run) to run.
    |
    */

    'check' => [
        'enabled' => (bool) env('API_KEY_ALERT_CHECK', false),
        'time' => env('API_KEY_ALERT_CHECK_TIME', '07:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Passive detection
    |--------------------------------------------------------------------------
    |
    | "http" inspects every response of the Laravel Http client to a host of a
    | service below. "exceptions" inspects every exception that is reported,
    | which covers SDKs that don't use the Http client, such as Mollie.
    |
    */

    'detect' => [
        'exceptions' => (bool) env('API_KEY_ALERT_DETECT_EXCEPTIONS', true),
        'http' => (bool) env('API_KEY_ALERT_DETECT_HTTP', true),
    ],

    'enabled' => (bool) env('API_KEY_ALERT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Mail
    |--------------------------------------------------------------------------
    |
    | "to" takes one address or a comma separated list. Without an address the
    | alert is logged and the events still fire. "queue" sends the mail through
    | the queue instead of right after the response.
    |
    */

    'mail' => [
        'queue' => (bool) env('API_KEY_ALERT_MAIL_QUEUE', false),
        'to' => env('API_KEY_ALERT_MAIL_TO'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Services
    |--------------------------------------------------------------------------
    |
    | hosts       Hosts the service answers on; "*" matches any subdomain.
    | detector    Class that recognises a rejected key in a response. Default:
    |             StatusDetector (HTTP 401 and 403).
    | exceptions  Exception classes that always mean a rejected key, for SDKs
    |             that throw without the HTTP response attached.
    | check       The request of the daily check. A check with an empty value
    |             in "query", "headers" or "token" is skipped.
    |
    */

    'services' => [

        'anthropic' => [
            'check' => [
                'headers' => [
                    'anthropic-version' => '2023-06-01',
                    'x-api-key' => env('ANTHROPIC_API_KEY'),
                ],
                'url' => 'https://api.anthropic.com/v1/models',
            ],
            'hosts' => ['api.anthropic.com'],
        ],

        'google' => [
            'check' => [
                'query' => [
                    'address' => 'Amsterdam',
                    'key' => env('GOOGLE_MAPS_API_KEY'),
                ],
                'url' => 'https://maps.googleapis.com/maps/api/geocode/json',
            ],
            'detector' => GoogleDetector::class,
            'hosts' => ['*.googleapis.com'],
        ],

        'messagebird' => [
            'exceptions' => ['MessageBird\Exceptions\AuthenticateException'],
            'hosts' => ['rest.messagebird.com'],
        ],

        'mollie' => [
            'check' => [
                'token' => env('MOLLIE_KEY'),
                'url' => 'https://api.mollie.com/v2/methods',
            ],
            'hosts' => ['api.mollie.com'],
        ],

        'openai' => [
            'check' => [
                'token' => env('OPENAI_API_KEY'),
                'url' => 'https://api.openai.com/v1/models',
            ],
            'hosts' => ['api.openai.com'],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Throttle
    |--------------------------------------------------------------------------
    |
    | At most one alert per service in this many minutes. A recovery resets it,
    | so a key that breaks again is reported straight away.
    |
    */

    'throttle_minutes' => (int) env('API_KEY_ALERT_THROTTLE_MINUTES', 360),

];
