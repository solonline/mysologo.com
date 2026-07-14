<?php

namespace App\Models;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Stripe\Enums\StripeFundingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CustomerStripeAccount extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('customer_stripe_accounts');
    }

    protected $fillable = [
        'customer_id',
        'stripe_customer_id',
        'stripe_payment_method_id',
        'currency',
        'status',
        'status_reason',
        'status_source',
        'status_changed_at',
        'funding_status',
        'funding_status_reason',
        'funding_status_changed_at',
        'viban',
        'funding_type',
        'bank_transfer_type',
        'funding_iban',
        'funding_bic',
        'funding_account_holder_name',
        'funding_country',
        'funding_financial_addresses',
    ];

    protected $casts = [
        'funding_financial_addresses' => 'array',
        'status' => IntegrationAccountStatus::class,
        'status_changed_at' => 'datetime',
        'funding_status' => StripeFundingStatus::class,
        'funding_status_changed_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
