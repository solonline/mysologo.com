<?php

namespace App\Domains\Customer\Mail;

use App\Domains\Customer\Enums\CustomerNotificationType;
use App\Models\CustomerNotification;
use App\Models\StripeTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerAppNotificationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public CustomerNotification $notification,
    ) {}

    public function envelope(): Envelope
    {
        $data = $this->data();

        if (in_array($this->notification->type, [
            CustomerNotificationType::TransferReceived->value,
            CustomerNotificationType::TopupImmediate->value,
        ], true)) {
            $amount = (string) ($data['amount_label'] ?? '');

            return new Envelope(
                subject: filled($amount)
                    ? 'Boas notícias! Recebeu '.$amount.' na Yoop'
                    : (string) $this->notification->title,
            );
        }

        if ($this->notification->type === CustomerNotificationType::TransferSent->value) {
            $amount = (string) ($data['amount_label'] ?? '');

            return new Envelope(
                subject: filled($amount)
                    ? 'Transferência de '.$amount.' enviada'
                    : (string) $this->notification->title,
            );
        }

        return new Envelope(
            subject: (string) $this->notification->title,
        );
    }

    public function content(): Content
    {
        $data = $this->data();

        if ($this->notification->type === CustomerNotificationType::TopupImmediate->value) {
            return $this->topupImmediateContent($data);
        }

        if ($this->notification->type === CustomerNotificationType::TransferReceived->value) {
            return $this->transferReceivedContent($data);
        }

        if ($this->notification->type === CustomerNotificationType::TransferSent->value) {
            return $this->transferSentContent($data);
        }

        return new Content(
            view: 'emails.customer_notification',
            with: [
                'customer' => $this->notification->customer,
                'title' => (string) $this->notification->title,
                'body' => $this->notification->body,
                'path' => (string) ($data['path'] ?? ''),
                'resourceId' => (string) ($data['resource_id'] ?? ''),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function topupImmediateContent(array $data): Content
    {
        $transactionId = (int) ($data['stripe_transaction_id'] ?? $data['resource_id'] ?? 0);
        $transaction = $transactionId > 0
            ? StripeTransaction::query()->find($transactionId)
            : null;

        if ($transaction === null) {
            $transaction = new StripeTransaction([
                'bank_transfer_sender_name' => $data['bank_transfer_sender_name'] ?? null,
                'bank_transfer_reference' => $data['bank_transfer_reference'] ?? null,
                'partner_funded_at' => $this->notification->created_at,
                'created_at' => $this->notification->created_at,
            ]);
        }

        $currency = strtoupper((string) ($data['currency'] ?? $transaction->currency ?? ''));
        $amountCents = (int) ($data['amount_cents'] ?? 0);
        $formattedAmount = $amountCents > 0
            ? number_format($amountCents / 100, 2, ',', '.')
            : (string) ($data['amount_label'] ?? '');

        return new Content(
            view: 'emails.bank_transfer_topup_received',
            with: [
                'customer' => $this->notification->customer,
                'transaction' => $transaction,
                'formattedAmount' => $formattedAmount,
                'currency' => $currency,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function transferReceivedContent(array $data): Content
    {
        $currency = strtoupper((string) ($data['currency'] ?? ''));
        $amountCents = (int) ($data['amount_cents'] ?? 0);
        $formattedAmount = $amountCents > 0
            ? number_format($amountCents / 100, 2, ',', '.')
            : (string) ($data['amount_label'] ?? '');

        return new Content(
            view: 'emails.transfer_received',
            with: [
                'customer' => $this->notification->customer,
                'sender' => (string) ($data['sender'] ?? ''),
                'formattedAmount' => $formattedAmount,
                'currency' => $currency,
                'description' => $this->notification->body,
                'creditedAt' => $this->notification->created_at,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function transferSentContent(array $data): Content
    {
        $currency = strtoupper((string) ($data['currency'] ?? ''));
        $amountCents = (int) ($data['amount_cents'] ?? 0);
        $formattedAmount = $amountCents > 0
            ? number_format($amountCents / 100, 2, ',', '.')
            : (string) ($data['amount_label'] ?? '');

        return new Content(
            view: 'emails.transfer_sent',
            with: [
                'customer' => $this->notification->customer,
                'recipient' => (string) ($data['recipient'] ?? ''),
                'formattedAmount' => $formattedAmount,
                'currency' => $currency,
                'description' => $this->notification->body,
                'sentAt' => $this->notification->created_at,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        return is_array($this->notification->data) ? $this->notification->data : [];
    }
}
