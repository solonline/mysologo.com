<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WiseLiquidityEvent extends Model
{
    protected $fillable = [
        'event_type',
        'delivery_id',
        'balance_id',
        'currency',
        'amount_cents',
        'post_transaction_balance',
        'channel_name',
        'occurred_at',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
            'post_transaction_balance' => 'decimal:4',
        ];
    }
}
