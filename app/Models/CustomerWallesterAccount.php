<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
        'viban'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
