<?php

namespace App\Models;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerEburyAccount extends Model
{
    protected $fillable = [
        'customer_id',
        'ebury_account_id',
        'currency',
        'status',
        'status_reason',
        'status_source',
        'status_changed_at',
        'iban',
        'metadata',
    ];

    protected $casts = [
        'status' => IntegrationAccountStatus::class,
        'status_changed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
