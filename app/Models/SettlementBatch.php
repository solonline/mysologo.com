<?php

namespace App\Models;

use App\Enums\SettlementBatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SettlementBatch extends Model
{
    protected $fillable = [
        'currency',
        'available_date',
        'total_amount',
        'transaction_count',
        'master_transfer_id',
        'master_transferred_at',
        'completed_at',
        'failed_at',
        'failure_reason',
        'status',
    ];

    protected $casts = [
        'available_date'        => 'date',
        'master_transferred_at' => 'datetime',
        'completed_at'          => 'datetime',
        'failed_at'             => 'datetime',
        'status'                => SettlementBatchStatus::class,
        'total_amount'          => 'integer',
        'transaction_count'     => 'integer',
    ];

    public function payouts(): HasMany
    {
        return $this->hasMany(CustomerPayout::class);
    }

    public function isPending(): bool
    {
        return $this->status === SettlementBatchStatus::PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === SettlementBatchStatus::COMPLETED;
    }
}
