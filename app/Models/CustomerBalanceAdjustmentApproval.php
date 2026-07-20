<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerBalanceAdjustmentApproval extends Model
{
    protected $fillable = [
        'adjustment_id',
        'user_id',
        'code',
        'code_expires_at',
        'verified_at',
        'sms_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'code_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'sms_sent_at' => 'datetime',
        ];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(CustomerBalanceAdjustment::class, 'adjustment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->code_expires_at === null || now()->greaterThan($this->code_expires_at);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
