<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int         $id
 * @property int         $customer_id
 * @property int|null    $tpa_id
 * @property string      $payment_method
 * @property string      $label
 * @property float       $fee_percentage
 * @property float       $fee_fixed
 * @property string      $currency
 * @property bool        $is_active
 */
class CustomerPaymentMethodFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'tpa_id',
        'payment_method',
        'label',
        'fee_percentage',
        'fee_fixed',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'fee_percentage' => 'decimal:4',
        'fee_fixed'      => 'decimal:4',
        'is_active'      => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function tpa()
    {
        return $this->belongsTo(Tpa::class);
    }

    public function isOnline(): bool
    {
        return is_null($this->tpa_id);
    }

    public function calculateFee(int $amountCents): array
    {
        $pct   = (float) $this->fee_percentage;
        $fixed = (float) $this->fee_fixed;

        $fee = (int) round($amountCents * $pct + $fixed * 100);

        return [
            'fee'        => $fee,
            'net_amount' => $amountCents - $fee,
        ];
    }
}
