<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $customer_id
 * @property int|null $tpa_id
 * @property int|null $merchant_id
 * @property string $payment_method
 * @property string $label
 * @property float $fee_percentage
 * @property float $fee_fixed
 * @property string $currency
 * @property bool $is_active
 */
class CustomerPaymentMethodFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'tpa_id',
        'merchant_id',
        'payment_method',
        'label',
        'fee_percentage',
        'fee_fixed',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'fee_percentage' => 'decimal:4',
        'fee_fixed' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function tpa()
    {
        return $this->belongsTo(Tpa::class);
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function isTpa(): bool
    {
        return $this->tpa_id !== null;
    }

    public function isMerchant(): bool
    {
        return $this->merchant_id !== null;
    }

    public function isOnline(): bool
    {
        return $this->tpa_id === null && $this->merchant_id === null;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForTpa(Builder $query, int $tpaId): Builder
    {
        return $query->where('tpa_id', $tpaId)->whereNull('merchant_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForMerchant(Builder $query, int $merchantId): Builder
    {
        return $query->where('merchant_id', $merchantId)->whereNull('tpa_id');
    }

    public static function findActiveForTpa(
        int $customerId,
        int $tpaId,
        string $paymentMethod,
        string $currency,
    ): ?self {
        return static::query()
            ->active()
            ->forTpa($tpaId)
            ->where('customer_id', $customerId)
            ->where('payment_method', $paymentMethod)
            ->where('currency', strtoupper($currency))
            ->first();
    }

    public static function findActiveForMerchant(
        int $customerId,
        int $merchantId,
        string $paymentMethod,
        string $currency,
    ): ?self {
        return static::query()
            ->active()
            ->forMerchant($merchantId)
            ->where('customer_id', $customerId)
            ->where('payment_method', $paymentMethod)
            ->where('currency', strtoupper($currency))
            ->first();
    }

    public function calculateFee(int $amountCents): array
    {
        $pct = (float) $this->fee_percentage;
        $fixed = (float) $this->fee_fixed;

        $fee = (int) round($amountCents * $pct + $fixed * 100);

        return [
            'fee' => $fee,
            'net_amount' => $amountCents - $fee,
        ];
    }
}
