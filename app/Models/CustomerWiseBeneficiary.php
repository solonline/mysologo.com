<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerWiseBeneficiary extends Model
{
    protected $fillable = [
        'customer_id',
        'wise_recipient_id',
        'wise_profile_id',
        'currency',
        'type',
        'account_holder_name',
        'owned_by_customer',
        'details',
        'ultimate_beneficiary',
        'wise_hash',
        'account_summary',
        'active',
    ];

    protected $casts = [
        'wise_recipient_id' => 'integer',
        'owned_by_customer' => 'boolean',
        'details' => 'array',
        'ultimate_beneficiary' => 'array',
        'active' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'wise_recipient_id' => $this->wise_recipient_id,
            'currency' => $this->currency,
            'type' => $this->type,
            'account_holder_name' => $this->account_holder_name,
            'owned_by_customer' => $this->owned_by_customer,
            'details' => $this->details,
            'ultimate_beneficiary' => $this->ultimate_beneficiary,
            'account_summary' => $this->account_summary,
            'active' => $this->active,
        ];
    }
}
