<?php

namespace App\Domains\Wise\Enums;

use App\Domains\Wallet\Enums\CustomerMovementStatus;

enum WiseTransferStatus: string
{
    case IncomingPaymentWaiting = 'incoming_payment_waiting';
    case IncomingPaymentInitiated = 'incoming_payment_initiated';
    case Processing = 'processing';
    case FundsConverted = 'funds_converted';
    case OutgoingPaymentSent = 'outgoing_payment_sent';
    case ChargedBack = 'charged_back';
    case Cancelled = 'cancelled';
    case FundsRefunded = 'funds_refunded';
    case BouncedBack = 'bounced_back';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::IncomingPaymentWaiting, self::IncomingPaymentInitiated => 'A caminho da Wise',
            self::Processing, self::FundsConverted => 'Em processamento',
            self::OutgoingPaymentSent => 'Pagamento enviado',
            self::ChargedBack => 'Valor devolvido ao remetente',
            self::Cancelled => 'Pagamento cancelado',
            self::FundsRefunded => 'Pagamento reembolsado',
            self::BouncedBack => 'Devolvido',
            self::Unknown => 'Estado desconhecido',
        };
    }

    public function customerStatus(): CustomerMovementStatus
    {
        return match ($this) {
            self::IncomingPaymentWaiting, self::IncomingPaymentInitiated => CustomerMovementStatus::Pending,
            self::Processing, self::FundsConverted => CustomerMovementStatus::Processing,
            self::OutgoingPaymentSent => CustomerMovementStatus::Sent,
            self::ChargedBack => CustomerMovementStatus::ChargedBack,
            self::Cancelled => CustomerMovementStatus::Canceled,
            self::FundsRefunded => CustomerMovementStatus::Refunded,
            self::BouncedBack => CustomerMovementStatus::BouncedBack,
            self::Unknown => CustomerMovementStatus::Unknown,
        };
    }

    public function returnsFunds(): bool
    {
        return in_array($this, [
            self::Cancelled,
            self::FundsRefunded,
            self::BouncedBack,
        ], true);
    }
}
