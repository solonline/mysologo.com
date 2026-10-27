<?php

namespace App\Domains\Merchant\Mail;

use App\Models\Merchant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SecurePayWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Merchant $merchant) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bem-vindo à SecurePay',
        );
    }

    public function content(): Content
    {
        $this->merchant->loadMissing('customer');

        return new Content(
            view: 'emails.securepay_welcome',
            with: [
                'customer' => $this->merchant->customer,
                'merchant' => $this->merchant,
                'docsUrl' => route('docs.partners'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
