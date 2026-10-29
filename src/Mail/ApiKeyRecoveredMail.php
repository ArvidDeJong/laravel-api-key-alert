<?php

namespace Darvis\LaravelApiKeyAlert\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ApiKeyRecoveredMail extends Mailable
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $service,
        public ?string $failedSince = null,
        public array $context = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('api-key-alert::mail.recovered.subject', [
                'service' => $this->service,
                'app' => (string) config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'api-key-alert::mail.recovered');
    }
}
