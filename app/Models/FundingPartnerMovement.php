<?php

namespace App\Models;

use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FundingPartnerMovement extends Model
{
    protected $fillable = [
        'provider',
        'funding_account_type',
        'funding_account_id',
        'external_account_id',
        'direction',
        'type',
        'amount',
        'currency',
        'transfer_group_id',
        'counterparty_external_account_id',
        'customer_wallester_card_id',
        'partner_external_id',
        'status',
        'source',
        'idempotency_key',
        'failure_reason',
        'reference_type',
        'reference_id',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'provider' => IntegrationProvider::class,
            'direction' => FundingPartnerMovementDirection::class,
            'type' => FundingPartnerMovementType::class,
            'status' => FundingPartnerMovementStatus::class,
            'source' => FundingPartnerMovementSource::class,
            'amount' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function fundingAccount(): MorphTo
    {
        return $this->morphTo();
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function wallesterCard(): BelongsTo
    {
        return $this->belongsTo(CustomerWallesterCard::class, 'customer_wallester_card_id');
    }
}
