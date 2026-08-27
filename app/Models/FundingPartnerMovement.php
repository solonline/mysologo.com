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
        'original_amount',
        'original_currency',
        'fees_amount',
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
            'original_amount' => 'integer',
            'fees_amount' => 'integer',
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

    public function fxDetailLabel(): ?string
    {
        if ($this->fees_amount === null || (int) $this->fees_amount <= 0) {
            return null;
        }

        $parts = [];

        if ($this->original_amount && $this->original_currency) {
            $parts[] = number_format(((int) $this->original_amount) / 100, 2, ',', '.').' '.strtoupper((string) $this->original_currency);
        }

        $parts[] = 'câmbio '.number_format(((int) $this->fees_amount) / 100, 2, ',', '.').' '.strtoupper((string) $this->currency);

        return implode(' · ', $parts);
    }

    public function isWallesterFxFee(): bool
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $envelopeType = strtolower((string) ($metadata['wallester_event_type'] ?? ''));

        if ($envelopeType === 'fee') {
            return true;
        }

        $transactionType = strtolower((string) ($metadata['transaction_type'] ?? $metadata['type'] ?? ''));

        return str_contains($transactionType, 'foreignexchange')
            || str_contains($transactionType, 'foreign_exchange')
            || str_contains($transactionType, 'fee');
    }
}
