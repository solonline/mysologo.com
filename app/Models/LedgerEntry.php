<?php

namespace App\Models;

use App\Domains\Wallet\Enums\LedgerEntryType;
use App\Domains\Wallet\Enums\TransactionDirection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LedgerEntry extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'customer_id',
        'currency',
        'direction',
        'amount',
        'balance_after',
        'entry_type',
        'reference_type',
        'reference_id',
        'description',
        'metadata',
    ];

    protected $casts = [
        'direction'  => TransactionDirection::class,
        'entry_type' => LedgerEntryType::class,
        'amount'     => 'integer',
        'balance_after' => 'integer',
        'metadata'   => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
