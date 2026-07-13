<?php

namespace App\Domains\Wallet\Mail;

use App\Models\Customer;
use App\Models\StripeTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BankTransferTopupReceivedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Customer $customer,
        public StripeTransaction $transaction,
        public string $formattedAmount,
        public string $currency,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Boas notícias! Recebeu '.$this->formattedAmount.' '.$this->currency.' na Yoop',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.bank_transfer_topup_received',
            with: [
                'customer' => $this->customer,
                'transaction' => $this->transaction,
                'formattedAmount' => $this->formattedAmount,
                'currency' => $this->currency,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
