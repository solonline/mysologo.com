<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Stripe\Enums\StripePaymentIntentPurpose;
use App\Domains\Stripe\Enums\StripePaymentIntentStatus;
use App\Domains\Wallet\Enums\TransactionType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $customer_id
 * @property string $stripe_payment_intent_id
 * @property int|null $stripe_payment_method_fee_id
 * @property int|null $tpa_id
 * @property int|null $stripe_transaction_id
 * @property string $type
 * @property string $purpose
 * @property int $amount
 * @property string $currency
 * @property string|null $payment_method_type
 * @property string $status
 * @property string|null $description
 * @property array|null $stripe_metadata
 * @property string|null $stripe_cash_balance_transaction_id
 * @property Carbon|null $expires_at
 * @property Carbon|null $canceled_at
 * @property Carbon|null $succeeded_at
 * @property Carbon|null $stripe_created_at
 */
class StripePaymentIntent extends Model
{
    use SoftDeletes;

    protected $table = 'stripe_payment_intents';

    protected $fillable = [
        'customer_id',
        'stripe_payment_intent_id',
        'stripe_payment_method_fee_id',
        'tpa_id',
        'stripe_transaction_id',
        'type',
        'purpose',
        'amount',
        'currency',
        'payment_method_type',
        'status',
        'description',
        'stripe_metadata',
        'stripe_cash_balance_transaction_id',
        'expires_at',
        'canceled_at',
        'succeeded_at',
        'stripe_created_at',
    ];

    protected $casts = [
        'stripe_metadata' => 'array',
        'expires_at' => 'datetime',
        'canceled_at' => 'datetime',
        'succeeded_at' => 'datetime',
        'stripe_created_at' => 'datetime',
    ];

    public function getStatusAttribute(mixed $value): StripePaymentIntentStatus
    {
        return StripePaymentIntentStatus::fromStripe(is_string($value) ? $value : null);
    }

    public function setStatusAttribute(StripePaymentIntentStatus|string $value): void
    {
        $this->attributes['status'] = $value instanceof StripePaymentIntentStatus ? $value->value : $value;
    }

    public function getPurposeAttribute(mixed $value): StripePaymentIntentPurpose
    {
        return StripePaymentIntentPurpose::tryFrom((string) $value) ?? StripePaymentIntentPurpose::Topup;
    }

    public function setPurposeAttribute(StripePaymentIntentPurpose|string $value): void
    {
        $this->attributes['purpose'] = $value instanceof StripePaymentIntentPurpose ? $value->value : $value;
    }

    public function getTypeAttribute(mixed $value): ?TransactionType
    {
        return TransactionType::tryFrom((string) $value);
    }

    public function setTypeAttribute(TransactionType|string $value): void
    {
        $this->attributes['type'] = $value instanceof TransactionType ? $value->value : $value;
    }

    /**
     * Tipo efectivo para criar StripeTransaction — nunca null.
     */
    public function resolveTransactionType(): TransactionType
    {
        if ($this->type instanceof TransactionType) {
            return $this->type;
        }

        $raw = $this->getRawOriginal('type')
            ?? $this->attributes['type']
            ?? null;

        if (is_string($raw) && $raw !== '') {
            $fromRaw = TransactionType::tryFrom($raw);
            if ($fromRaw instanceof TransactionType) {
                return $fromRaw;
            }
        }

        return match ($this->purpose) {
            StripePaymentIntentPurpose::MerchantPayment => TransactionType::MERCHANT_PAYMENT,
            StripePaymentIntentPurpose::Terminal => TransactionType::CARD_PRESENT,
            StripePaymentIntentPurpose::BankTransferTopup => TransactionType::BANK_TRANSFER,
            default => TransactionType::TOPUP,
        };
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function stripeTransaction(): BelongsTo
    {
        return $this->belongsTo(StripeTransaction::class, 'stripe_transaction_id');
    }

    public function paymentMethodFee(): BelongsTo
    {
        return $this->belongsTo(StripePaymentMethodFee::class, 'stripe_payment_method_fee_id');
    }

    public function tpa(): BelongsTo
    {
        return $this->belongsTo(Tpa::class);
    }
}
