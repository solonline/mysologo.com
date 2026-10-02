<?php

namespace App\Domains\Reward\Enums;

enum RewardStatus: string
{
    case Pending = 'pending';
    case Eligible = 'eligible';
    case Cancelled = 'cancelled';
    case Paid = 'paid';

    public function isPayable(): bool
    {
        return $this === self::Eligible;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Eligible => 'Elegível',
            self::Cancelled => 'Cancelado',
            self::Paid => 'Pago',
        };
    }
}
