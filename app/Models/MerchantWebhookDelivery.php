<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $merchant_id
 * @property int|null $merchant_payment_request_id
 * @property string $delivery_id
 * @property string $event_id
 * @property string $event_type
 * @property string $destination_url
 * @property array $request_body
 * @property int|null $response_status
 * @property string|null $response_body
 * @property int $attempt
 * @property int|null $duration_ms
 * @property string|null $error_message
 * @property Carbon $sent_at
 */
class MerchantWebhookDelivery extends Model
{
    protected $fillable = [
        'merchant_id',
        'merchant_payment_request_id',
        'delivery_id',
        'event_id',
        'event_type',
        'destination_url',
        'request_body',
        'response_status',
        'response_body',
        'attempt',
        'duration_ms',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'request_body' => 'array',
        'sent_at' => 'datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(MerchantPaymentRequest::class, 'merchant_payment_request_id');
    }
}
