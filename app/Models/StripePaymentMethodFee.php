<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int    $id
 * @property string $payment_method
 * @property string $label
 * @property string $currency
 * @property bool   $is_active
 * @property float  $stripe_fee_percentage
 * @property float  $stripe_fee_fixed
 * @property float  $platform_fee_percentage
 * @property float  $platform_fee_fixed
 */
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
     * Devolve o identificador Stripe real (ex: 'card_eea' → 'card').
     * Necessário porque as nossas categorias internas não correspondem 1:1 ao Stripe.
     */
    public function stripePaymentMethodType(): string
    {
        if (str_starts_with($this->payment_method, 'card_')) {
            return 'card';
        }

        return $this->payment_method;
    }

    public function calculatePlatformFee(int $amountCents): int
    {
        $platPct   = (float) $this->platform_fee_percentage;
        $platFixed = (float) $this->platform_fee_fixed;

        return (int) round($amountCents * $platPct + $platFixed * 100);
    }

    /**
     * Calcula a taxa total (Stripe + plataforma) para um dado valor em centavos.
     * Retorna os componentes separados para registo na transação.
     */
    public function calculateFees(int $amountCents): array
    {
        $stripePct   = (float) $this->stripe_fee_percentage;
        $stripeFixed = (float) $this->stripe_fee_fixed;
        $platPct     = (float) $this->platform_fee_percentage;
        $platFixed   = (float) $this->platform_fee_fixed;

        $stripeFee   = (int) round($amountCents * $stripePct + $stripeFixed * 100);
        $platformFee = (int) round($amountCents * $platPct + $platFixed * 100);

        return [
            'stripe_fee'   => $stripeFee,
            'platform_fee' => $platformFee,
            'net_amount'   => $amountCents - $stripeFee - $platformFee,
        ];
    }
}
