<?php

namespace App\Domains\Wallet\Enums;

enum CustomerMovementStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
    case Completed = 'completed';
    case Failed = 'failed';
    case Canceled = 'canceled';
    case Refunded = 'refunded';
    case ChargedBack = 'charged_back';
    case BouncedBack = 'bounced_back';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Processing => 'Em processamento',
            self::Sent => 'Enviado',
            self::Completed => 'Concluído',
            self::Failed => 'Falhou',
            self::Canceled => 'Cancelado',
            self::Refunded => 'Reembolsado',
            self::ChargedBack => 'Devolvido ao remetente',
            self::BouncedBack => 'Devolvido',
            self::Unknown => 'Estado desconhecido',
        };
    }
}
