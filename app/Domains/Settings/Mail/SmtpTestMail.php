<?php

namespace App\Domains\Settings\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SmtpTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $smtpHost,
        public int $smtpPort,
        public string $smtpEncryption,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Yoop — teste SMTP',
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.smtp_test',
            with: [
                'smtpHost' => $this->smtpHost,
                'smtpPort' => $this->smtpPort,
                'smtpEncryption' => $this->smtpEncryption,
                'sentAt' => now()->toDateTimeString(),
            ],
        );
    }
}
