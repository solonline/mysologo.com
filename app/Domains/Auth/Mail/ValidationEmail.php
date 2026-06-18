<?php

namespace App\Domains\Auth\Mail;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ValidationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public string $validationUrl,
        public array $infoSystem = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirmation of your email address',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.validation_email',
            with: [
                'customer' => $this->customer,
                'validationUrl' => $this->validationUrl,
                'infoSystem' => $this->infoSystem,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
