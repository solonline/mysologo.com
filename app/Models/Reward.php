<?php

namespace App\Models;

use App\Domains\Reward\Enums\RewardStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reward extends Model
{
    protected $fillable = [
        'customer_id',
        'program_id',
        'referred_id',
        'status',
        'qualified_at',
        'amount_cents',
        'currency',
        'paid_at',
        'ledger_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => RewardStatus::class,
            'qualified_at' => 'datetime',
            'paid_at' => 'datetime',
            'amount_cents' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(RewardProgram::class, 'program_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_id');
    }
}
