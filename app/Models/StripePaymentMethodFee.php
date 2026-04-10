<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StripePaymentMethodFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_method',
        'label',
        'stripe_fee_percentage',
        'stripe_fee_fixed',
        'platform_fee_percentage',
        'platform_fee_fixed',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'stripe_fee_percentage'   => 'decimal:4',
        'stripe_fee_fixed'        => 'decimal:4',
        'platform_fee_percentage' => 'decimal:4',
        'platform_fee_fixed'      => 'decimal:4',
        'is_active'               => 'boolean',
    ];

    /**
     * Calcula a taxa total (Stripe + plataforma) para um dado valor em centavos.
     * Retorna os componentes separados para registo na transação.
     */
    public function calculateFees(int $amountCents): array
    {
        $stripeFee = (int) round(
            $amountCents * $this->stripe_fee_percentage + $this->stripe_fee_fixed * 100
        );

        $platformFee = (int) round(
            $amountCents * $this->platform_fee_percentage + $this->platform_fee_fixed * 100
        );

        return [
            'stripe_fee'   => $stripeFee,
            'platform_fee' => $platformFee,
            'net_amount'   => $amountCents - $platformFee,
        ];
    }
}
