<?php

namespace App\Models;

use App\Domains\Wallet\Enums\CustomerPayoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CustomerPayout extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('customer_payouts');
    }

    protected $fillable = [
        'settlement_batch_id',
        'customer_id',
        'amount',
        'currency',
        'iban',
        'bic',
        'account_holder_name',
        'external_transfer_id',
        'processed_at',
        'status',
        'failure_reason',
    ];

    protected $casts = [
        'status'       => CustomerPayoutStatus::class,
        'processed_at' => 'datetime',
        'amount'       => 'integer',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SettlementBatch::class, 'settlement_batch_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
