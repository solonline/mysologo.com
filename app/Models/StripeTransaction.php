<?php

namespace App\Models;

use App\Domains\Wallet\Enums\PartnerFundingStatus;
use App\Domains\Wallet\Enums\TopupSettlementRoute;
use App\Domains\Wallet\Enums\TransactionDirection;
use App\Domains\Wallet\Enums\TransactionType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $customer_id
 * @property int|null $stripe_payment_method_fee_id
 * @property int|null $related_transaction_id
 * @property string $type
 * @property string $direction
 * @property string|null $stripe_payment_intent_id
 * @property string|null $stripe_charge_id
 * @property string $payment_method_type
 * @property int $amount
 * @property int $stripe_fee
 * @property int $platform_fee
 * @property int $net_amount
 * @property string $currency
 * @property string $status
 * @property string|null $description
 * @property array|null $stripe_metadata
 * @property Carbon|null $stripe_created_at
 */
class StripeTransaction extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('stripe_transactions');
    }

    protected $table = 'stripe_transactions';

    protected $fillable = [
        'customer_id',
        'tpa_id',
        'stripe_payment_method_fee_id',
        'related_transaction_id',
        'type',
        'direction',
        'stripe_payment_intent_id',
        'stripe_charge_id',
        'stripe_cash_balance_transaction_id',
        'payment_method_type',
        'amount',
        'stripe_fee',
        'platform_fee',
        'net_amount',
        'immediate_gross_amount',
        'wallester_funding_net_amount',
        'stripe_deferred_net_amount',
        'currency',
        'stripe_account_currency',
        'status',
        'description',
        'stripe_metadata',
        'bank_transfer_type',
        'bank_transfer_reference',
        'bank_transfer_sender_name',
        'bank_transfer_bic',
        'bank_transfer_iban_last4',
        'bank_transfer_amount',
        'bank_transfer_fee',
        'balance_settled_at',
        'partner_funding_status',
        'partner_funding_failure_reason',
        'partner_funded_at',
        'ebury_transfer_id',
        'ebury_settled_at',
        'stripe_created_at',
        'settles_at',
        'available_on',
    ];

    protected $casts = [
        'direction' => TransactionDirection::class,
        'stripe_metadata' => 'array',
        'stripe_created_at' => 'datetime',
        'settles_at' => 'datetime',
        'balance_settled_at' => 'datetime',
        'available_on' => 'date',
        'partner_funding_status' => PartnerFundingStatus::class,
        'partner_funded_at' => 'datetime',
        'ebury_settled_at' => 'datetime',
    ];

    public function getTypeAttribute(mixed $value): ?TransactionType
    {
        return TransactionType::tryFrom((string) $value);
    }

    public function setTypeAttribute(TransactionType|string $value): void
    {
        $this->attributes['type'] = $value instanceof TransactionType ? $value->value : $value;
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function tpa()
    {
        return $this->belongsTo(Tpa::class);
    }

    public function paymentMethodFee()
    {
        return $this->belongsTo(StripePaymentMethodFee::class, 'stripe_payment_method_fee_id');
    }

    public function relatedStripeTransaction()
    {
        return $this->belongsTo(StripeTransaction::class, 'related_transaction_id');
    }

    public function topupSettlementRoute(): ?TopupSettlementRoute
    {
        return TopupSettlementRoute::resolve($this);
    }
}
