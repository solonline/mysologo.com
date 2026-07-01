<?php

namespace App\Models;

use App\Domains\Wallester\Enums\Wallester3dsChallengeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallester3dsChallenge extends Model
{
    protected $table = 'wallester_3ds_challenges';

    protected $fillable = [
        'wallester_request_id',
        'wallester_x_request_id',
        'customer_id',
        'customer_wallester_card_id',
        'wallester_card_id',
        'status',
        'card',
        'merchant',
        'transaction',
        'expires_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => Wallester3dsChallengeStatus::class,
            'card' => 'array',
            'merchant' => 'array',
            'transaction' => 'array',
            'expires_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function wallesterCard(): BelongsTo
    {
        return $this->belongsTo(CustomerWallesterCard::class, 'customer_wallester_card_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isResolvable(): bool
    {
        return $this->status === Wallester3dsChallengeStatus::Pending && ! $this->isExpired();
    }
}
