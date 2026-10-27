<?php

namespace App\Models;

use App\Domains\Wise\Enums\WiseTransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WiseTransfer extends Model
{
    protected $fillable = [
        'customer_id',
        'wise_transfer_id',
        'wise_batch_group_id',
        'wise_recipient_id',
        'beneficiary_name',
        'reference',
        'currency',
        'amount_cents',
        'fee_cents',
        'fee_currency',
        'idempotency_key',
        'status',
        'settled_at',
        'receipt_sent_at',
        'refunded_at',
    ];

    protected $casts = [
        'wise_transfer_id' => 'integer',
        'wise_recipient_id' => 'integer',
        'amount_cents' => 'integer',
        'fee_cents' => 'integer',
        'status' => WiseTransferStatus::class,
        'settled_at' => 'datetime',
        'receipt_sent_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
