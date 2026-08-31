<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $merchant_id
 * @property int|null $payer_id
 * @property int|null $transaction_id
 * @property int $amount
 * @property string $currency
 * @property string|null $description
 * @property string|null $stripe_payment_intent_id
 * @property string|null $external_reference
 * @property string|null $webhook_url
 * @property array|null $metadata
 * @property array|null $items
 * @property string $language
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $paid_at
 * @property-read StripePaymentIntent|null $stripePaymentIntent
 */
class MerchantPaymentRequest extends Model
{
    use HasFactory;

    protected $table = 'merchant_payment_requests';

    protected $fillable = [
        'uuid',
        'merchant_id',
        'payer_id',
        'transaction_id',
        'stripe_payment_intent_id',
        'language',
        'amount',
        'currency',
        'description',
        'external_reference',
        'webhook_url',
        'metadata',
        'items',
        'payment_method_types',
        'status',
        'expires_at',
        'paid_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'items' => 'array',
        'payment_method_types' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->uuid ??= (string) Str::uuid();
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isPayable(): bool
    {
        return \in_array($this->status, ['pending', 'processing']) && ! $this->isExpired() && ! $this->paid_at;
    }

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function stripePaymentIntent()
    {
        return $this->belongsTo(StripePaymentIntent::class, 'stripe_payment_intent_id', 'stripe_payment_intent_id');
    }

    public function payer()
    {
        return $this->belongsTo(Payer::class);
    }

    public function stripeTransaction()
    {
        return $this->belongsTo(StripeTransaction::class, 'transaction_id');
    }
}
