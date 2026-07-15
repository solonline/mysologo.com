<?php

namespace App\Domains\Onboarding\DTOs;

use App\Domains\Onboarding\Enums\IntegrationAccountStatus;
use App\Domains\Onboarding\Enums\IntegrationProvider;

readonly class IntegrationAccountState
{
    public function __construct(
        public IntegrationProvider $provider,
        public ?IntegrationAccountStatus $status,
        public ?string $reason = null,
        public ?string $source = null,
        public ?string $updatedAt = null,
        public array $blockedActions = [],
        public ?array $iban = null,
    ) {}

    public function toArray(): array
    {
        return [
            'provider' => $this->provider->value,
            'label' => $this->provider->label(),
            'status' => $this->status?->value,
            'reason' => $this->reason,
            'source' => $this->source,
            'updated_at' => $this->updatedAt,
            'blocked_actions' => $this->blockedActions,
            'iban' => $this->iban,
        ];
    }
}
