<?php

namespace Darvis\LaravelApiKeyAlert\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class EnvironmentFailedMail extends Mailable
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context  Already redacted; "settings" holds config key => [actual, expected].
     */
    public function __construct(
        public string $reason,
        public array $context = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('api-key-alert::mail.environment.failed.subject', ['app' => (string) config('app.name')]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'api-key-alert::mail.environment-failed');
    }
}
