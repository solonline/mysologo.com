<?php

namespace App\Domains\Backoffice\DTOs;

use App\Domains\Backoffice\Requests\StoreRewardProgramRequest;
use App\Domains\Backoffice\Requests\UpdateRewardProgramRequest;
use Carbon\Carbon;
use Carbon\CarbonInterface;

readonly class RewardProgramData
{
    public function __construct(
        public ?string $slug,
        public string $name,
        public ?string $description,
        public bool $isActive,
        public ?CarbonInterface $validFrom,
        public ?CarbonInterface $validUntil,
    ) {}

    public static function fromRequest(StoreRewardProgramRequest|UpdateRewardProgramRequest $request): self
    {
        $validated = $request->validated();
        $description = trim((string) ($validated['description'] ?? ''));

        return new self(
            slug: isset($validated['slug']) ? (string) $validated['slug'] : null,
            name: (string) $validated['name'],
            description: $description !== '' ? $description : null,
            isActive: $request->boolean('is_active'),
            validFrom: ! empty($validated['valid_from']) ? Carbon::parse($validated['valid_from']) : null,
            validUntil: ! empty($validated['valid_until']) ? Carbon::parse($validated['valid_until']) : null,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        $attributes = [
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->isActive,
            'valid_from' => $this->validFrom,
            'valid_until' => $this->validUntil,
        ];

        if ($this->slug !== null) {
            $attributes['slug'] = $this->slug;
        }

        return $attributes;
    }
}
