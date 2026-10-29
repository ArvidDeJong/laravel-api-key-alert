<?php

return [

    'failed' => [
        'heading' => 'The :service API key no longer works',
        'intro' => ':service rejected the API key of :app. The provider gave this reason:',
        'next_steps' => 'Check the key and its restrictions (allowed IP addresses, referrers and enabled APIs) and the billing of the account. The reason above often names the IP address the request came from.',
        'next_steps_heading' => 'What to check',
        'subject' => '[:app] The :service API key no longer works',
        'throttle' => 'You get at most one mail per hour about this service, and a mail once the key works again.|You get at most one mail per :hours hours about this service, and a mail once the key works again.',
    ],

    'fields' => [
        'app' => 'Application',
        'app_url' => 'URL',
        'environment' => 'Environment',
        'exception' => 'Exception',
        'server' => 'Server',
        'source' => 'Found by',
        'status' => 'HTTP status',
        'time' => 'Time',
        'url' => 'Request',
    ],

    'recovered' => [
        'heading' => 'The :service API key works again',
        'intro' => ':service accepts the API key of :app again.',
        'since' => 'It was rejected from :since.',
        'subject' => '[:app] The :service API key works again',
    ],

];
