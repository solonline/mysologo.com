<?php

namespace App\Models;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerWallesterAccount extends Model
{
    protected $fillable = [
        'customer_id',
        'wallester_account_id',
        'currency_code',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(CustomerWallesterCard::class);
    }
}
