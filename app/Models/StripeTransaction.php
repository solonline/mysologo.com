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

    /**
     * Other customer in a P2P pair (via related_transaction_id).
     */
    public function p2pCounterparty(): ?Customer
    {
        if ($this->type !== TransactionType::P2P) {
            return null;
        }

        return $this->relatedStripeTransaction?->customer;
    }

    /**
     * Role of the counterparty relative to this transaction leg.
     * Debit (send) → recipient; credit (receive) → sender.
     */
    public function p2pCounterpartyRole(): ?string
    {
        if ($this->type !== TransactionType::P2P) {
            return null;
        }

        $direction = $this->direction instanceof TransactionDirection
            ? $this->direction
            : TransactionDirection::tryFrom((string) $this->direction);

        return $direction === TransactionDirection::Debit ? 'recipient' : 'sender';
    }

    public function stripePaymentIntent()
    {
        return $this->hasOne(StripePaymentIntent::class, 'stripe_transaction_id');
    }

    public function topupSettlementRoute(): ?TopupSettlementRoute
    {
        return TopupSettlementRoute::resolve($this);
    }

    /**
     * Fees are applied asynchronously via charge.updated after payment_intent.succeeded.
     * Until then net_amount is stored as 0 in the database but must not be exposed as zero.
     */
    public function feesPending(): bool
    {
        return $this->amount > 0
            && $this->net_amount === 0
            && $this->stripe_fee === 0
            && $this->platform_fee === 0;
    }

    public function resolvedNetAmount(): ?int
    {
        return $this->feesPending() ? null : $this->net_amount;
    }

    public function resolvedFeeTotal(): ?int
    {
        return $this->feesPending() ? null : $this->stripe_fee + $this->platform_fee;
    }

    /**
     * Transferência bancária segue a data de liquidez da Stripe (available_on),
     * mesmo que a main já tenha adiantado o valor.
     */
    public function customerFundsAvailable(): bool
    {
        if ((string) $this->status !== 'succeeded' || $this->awaitsStripeLiquidityDate()) {
            return false;
        }

        return $this->balance_settled_at !== null;
    }

    /**
     * O pagamento Stripe pode já estar succeeded. Para o cliente, uma transferência
     * com liquidez futura — ou ainda sem payout — fica pending até essa data.
     */
    public function customerFacingStatus(): string
    {
        $status = (string) $this->status;

        if ($status === 'succeeded' && ! $this->customerFundsAvailable()) {
            return 'pending';
        }

        return $status;
    }

    public function awaitsStripeLiquidityDate(): bool
    {
        if ($this->payment_method_type !== 'bank_transfer' && blank($this->stripe_cash_balance_transaction_id)) {
            return false;
        }

        $availableOn = $this->available_on?->timezone(config('app.timezone'))->startOfDay();

        if ($availableOn === null) {
            return false;
        }

        return $availableOn->isAfter(now()->timezone(config('app.timezone'))->startOfDay());
    }
}
