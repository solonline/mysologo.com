<?php

namespace App\Http\Resources;

use App\Models\Reward;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Reward */
class RewardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status?->value ?? $this->status,
            'qualified_at' => $this->qualified_at?->toIso8601String(),
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'program' => $this->whenLoaded('program', fn () => $this->program ? [
                'slug' => $this->program->slug,
                'name' => $this->program->name,
                'description' => $this->program->description,
                'valid_from' => $this->program->valid_from?->toIso8601String(),
                'valid_until' => $this->program->valid_until?->toIso8601String(),
            ] : null),
            'referred' => $this->whenLoaded('referred', fn () => $this->referred ? [
                'id' => $this->referred->id,
                'name' => $this->referred->name,
                'username' => $this->referred->username,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
