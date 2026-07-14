<?php

namespace App\Models;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string|null $wallester_oob_id
 */
class CustomerWallesterAccount extends Model
{
    protected $fillable = [
        'customer_id',
        'wallester_account_id',
        'wallester_oob_id',
        'currency_code',
        'funding_currency',
        'product_id',
        'company_id',
        'credit_limit',
        'used_credit',
        'balance',
        'available_amount',
        'blocked_amount',
        'blocked_credit_amount',
        'status',
        'status_reason',
        'status_source',
        'status_changed_at',
        'daily_purchase',
        'daily_withdrawal',
        'daily_internet_purchase',
        'daily_contactless_purchase',
        'weekly_purchase',
        'weekly_withdrawal',
        'weekly_internet_purchase',
        'weekly_contactless_purchase',
        'monthly_purchase',
        'monthly_withdrawal',
        'monthly_internet_purchase',
        'monthly_contactless_purchase',
        'is_main',
        'cards_count',
        'iban',
        'swift_code',
        'receiver_name',
        'payment_details',
        'bank_name',
        'bank_address',
        'registration_number',
        'reference_number',
        'viban',
    ];

    protected $casts = [
        'status' => IntegrationAccountStatus::class,
        'status_changed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CustomerWallesterAccount $account): void {
            if ($account->wallester_oob_id === null) {
                $account->wallester_oob_id = (string) Str::uuid();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(CustomerWallesterCard::class);
    }

    /**
     * @param  callable(float|int|string|null): (?int)  $amountToCents
     */
    public function syncBalancesFromWallesterResponse(array $response, callable $amountToCents): void
    {
        $this->update([
            'balance' => $this->nullableMoneyToCents($response['balance'] ?? null, $amountToCents),
            'available_amount' => $this->nullableMoneyToCents($response['available_amount'] ?? null, $amountToCents),
            'blocked_amount' => $this->nullableMoneyToCents($response['blocked_amount'] ?? null, $amountToCents),
            'blocked_credit_amount' => $this->nullableMoneyToCents($response['blocked_credit_amount'] ?? null, $amountToCents),
            'credit_limit' => $this->nullableMoneyToCents($response['credit_limit'] ?? null, $amountToCents),
            'used_credit' => $this->nullableMoneyToCents($response['used_credit'] ?? null, $amountToCents),
        ]);
    }

    /**
     * @param  callable(float|int|string|null): (?int)  $amountToCents
     */
    private function nullableMoneyToCents(float|int|string|null $amount, callable $amountToCents): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return $amountToCents($amount);
    }
}
