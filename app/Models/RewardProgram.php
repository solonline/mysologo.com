<?php

namespace App\Models;

use App\Domains\Reward\Enums\RewardProgramSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RewardProgram extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_active',
        'valid_from',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
        ];
    }

    public static function referral(): self
    {
        return static::query()
            ->where('slug', RewardProgramSlug::Referral->value)
            ->firstOrFail();
    }

    public function isReferral(): bool
    {
        return $this->slug === RewardProgramSlug::Referral->value;
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class, 'program_id');
    }
}
