<?php

namespace Darvis\LaravelApiKeyAlert\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ApiKeyFailedMail extends Mailable
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context  Already redacted.
     */
    public function __construct(
        public string $service,
        public string $reason,
        public array $context = [],
        public int $throttleMinutes = 360,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('api-key-alert::mail.failed.subject', [
                'service' => $this->service,
                'app' => (string) config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'api-key-alert::mail.failed');
    }
}
