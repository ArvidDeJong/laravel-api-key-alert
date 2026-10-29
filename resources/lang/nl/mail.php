<?php

return [

    'failed' => [
        'heading' => 'De API-sleutel van :service werkt niet meer',
        'intro' => ':service weigert de API-sleutel van :app. De provider gaf deze reden:',
        'next_steps' => 'Controleer de sleutel en zijn beperkingen (toegestane IP-adressen, referrers en ingeschakelde API\'s) en de facturering van het account. De reden hierboven noemt vaak het IP-adres waar het verzoek vandaan kwam.',
        'next_steps_heading' => 'Wat te controleren',
        'subject' => '[:app] De API-sleutel van :service werkt niet meer',
        'throttle' => 'Je krijgt over deze dienst hooguit één mail per uur, en een mail zodra de sleutel weer werkt.|Je krijgt over deze dienst hooguit één mail per :hours uur, en een mail zodra de sleutel weer werkt.',
    ],

    'fields' => [
        'app' => 'Applicatie',
        'app_url' => 'URL',
        'environment' => 'Omgeving',
        'exception' => 'Exception',
        'server' => 'Server',
        'source' => 'Gevonden door',
        'status' => 'HTTP-status',
        'time' => 'Tijdstip',
        'url' => 'Verzoek',
    ],

    'recovered' => [
        'heading' => 'De API-sleutel van :service werkt weer',
        'intro' => ':service accepteert de API-sleutel van :app weer.',
        'since' => 'Hij werd geweigerd vanaf :since.',
        'subject' => '[:app] De API-sleutel van :service werkt weer',
    ],

];
