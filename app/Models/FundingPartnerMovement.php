<?php

namespace App\Models;

use App\Domains\Onboarding\Enums\IntegrationProvider;
use App\Domains\Wallet\Enums\FundingPartnerMovementDirection;
use App\Domains\Wallet\Enums\FundingPartnerMovementSource;
use App\Domains\Wallet\Enums\FundingPartnerMovementStatus;
use App\Domains\Wallet\Enums\FundingPartnerMovementType;
use Illuminate\Database\Eloquent\Builder;
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
        'platform_fees_amount',
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
            'platform_fees_amount' => 'integer',
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

    public function counterpartyWallesterAccount(): BelongsTo
    {
        return $this->belongsTo(CustomerWallesterAccount::class, 'counterparty_external_account_id', 'wallester_account_id');
    }

    public function fxDetailLabel(): ?string
    {
        if ($this->type === FundingPartnerMovementType::Fee) {
            return null;
        }

        if (! $this->original_amount || ! $this->original_currency) {
            return null;
        }

        $originalCurrency = strtoupper((string) $this->original_currency);
        $accountCurrency = strtoupper((string) $this->currency);

        if ($originalCurrency === '' || $originalCurrency === $accountCurrency) {
            return null;
        }

        return number_format(((int) $this->original_amount) / 100, 2, ',', '.').' '.$originalCurrency
            .' → '
            .number_format(((int) $this->amount) / 100, 2, ',', '.').' '.$accountCurrency;
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

    public function isInternalPlatformFxTransfer(): bool
    {
        $key = (string) $this->idempotency_key;

        return $key !== '' && str_starts_with($key, 'fx-platform:');
    }

    public function isP2pTransfer(): bool
    {
        $key = (string) $this->idempotency_key;

        return $key !== '' && str_starts_with($key, 'p2p:');
    }

    public function isPlatformBalanceAdjustment(): bool
    {
        $key = (string) $this->idempotency_key;
        if ($key !== '' && str_starts_with($key, 'manual_adj:')) {
            return true;
        }

        $type = (string) $this->reference_type;

        return $type === CustomerBalanceAdjustment::class
            || $type === (new CustomerBalanceAdjustment)->getMorphClass();
    }

    public function isStripePlatformFeeTransfer(): bool
    {
        $key = (string) $this->idempotency_key;

        return $key !== '' && str_starts_with($key, 'stripe-platform-fee:');
    }

    /**
     * Débito fx-platform no cliente retail duplica a linha Câmbio.
     * O débito physical-card-platform-fee duplica a taxa de emissão (o customer vê só o total).
     * O crédito na master/subconta (:in) deve aparecer na conta plataforma.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCustomerVisible($query)
    {
        return $query->where(function ($visible): void {
            $visible->whereNull('idempotency_key')
                ->orWhere(function ($visible): void {
                    $visible
                        ->where('idempotency_key', 'not like', 'fx-platform:%:out')
                        ->where('idempotency_key', 'not like', 'physical-card-platform-fee:%:out');
                });
        });
    }

    public function displayTypeLabel(): string
    {
        if ($this->isP2pTransfer()) {
            return 'Transferência entre contas';
        }

        if ($this->isPlatformBalanceAdjustment()) {
            return $this->direction === FundingPartnerMovementDirection::In
                ? 'Crédito Yoop'
                : 'Débito Yoop';
        }

        if ($this->isInternalPlatformFxTransfer()) {
            return 'Câmbio Yoop';
        }

        if ($this->isStripePlatformFeeTransfer()) {
            return 'Taxa Yoop';
        }

        return match ($this->type) {
            FundingPartnerMovementType::AccountTransfer => 'Transferência',
            FundingPartnerMovementType::CardPurchase => 'Compra cartão',
            FundingPartnerMovementType::CardRefund => 'Reembolso cartão',
            FundingPartnerMovementType::InboundPayment => 'Entrada',
            FundingPartnerMovementType::Fee => 'Câmbio',
            FundingPartnerMovementType::VirtualCardIssuanceFee => 'Taxa emissão cartão virtual',
            FundingPartnerMovementType::PhysicalCardIssuanceFee => 'Taxa emissão cartão físico',
            default => $this->type?->value ?? 'Wallester',
        };
    }

    public function displaySourceLabel(): string
    {
        if ($this->isPlatformBalanceAdjustment()) {
            return $this->direction === FundingPartnerMovementDirection::In
                ? 'Crédito Yoop'
                : 'Débito Yoop';
        }

        return 'Wallester';
    }

    public function displayDescription(): ?string
    {
        if ($this->isStripePlatformFeeTransfer()) {
            return $this->description ?: 'Taxa Yoop';
        }

        if (! $this->isInternalPlatformFxTransfer()) {
            return $this->description;
        }

        $purchase = $this->reference;
        $merchant = $purchase instanceof self ? trim((string) $purchase->description) : '';

        return $merchant !== '' ? 'Câmbio Yoop · '.$merchant : 'Câmbio Yoop';
    }
}
