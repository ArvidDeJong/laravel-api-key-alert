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

    'environment' => [
        'failed' => [
            'heading' => 'De omgevingsinstellingen van :app kloppen niet',
            'intro' => ':app draait met instellingen die op deze server niet actief horen te zijn:',
            'next_steps' => 'Open het .env-bestand op de server, zet de waarden goed en draai "php artisan config:clear" (of "config:cache" als de config gecachet is).',
            'next_steps_heading' => 'Wat te doen',
            'reminder' => 'Je krijgt deze mail opnieuw na elke dagelijkse controle die nog een verkeerde instelling vindt, en nog één keer zodra alles klopt.',
            'setting' => 'is :actual, verwacht :expected',
            'subject' => '[:app] De omgevingsinstellingen kloppen niet',
        ],
        'recovered' => [
            'heading' => 'De omgevingsinstellingen van :app kloppen weer',
            'intro' => 'Elke instelling die de omgevingscontrole bewaakt heeft weer de verwachte waarde.',
            'since' => 'Ze klopten niet vanaf :since.',
            'subject' => '[:app] De omgevingsinstellingen kloppen weer',
        ],
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
