<?php

namespace App\Models;

use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentDirection;
use App\Domains\Backoffice\Enums\CustomerBalanceAdjustmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerBalanceAdjustment extends Model
{
    protected $fillable = [
        'customer_id',
        'direction',
        'amount_cents',
        'currency',
        'description',
        'status',
        'required_approvals',
        'requested_by',
        'idempotency_key',
        'partner_external_id',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'direction' => CustomerBalanceAdjustmentDirection::class,
            'status' => CustomerBalanceAdjustmentStatus::class,
            'amount_cents' => 'integer',
            'required_approvals' => 'integer',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(CustomerBalanceAdjustmentApproval::class, 'adjustment_id');
    }

    public function isPending(): bool
    {
        return $this->status === CustomerBalanceAdjustmentStatus::PendingApproval;
    }

    public function verifiedApprovalsCount(): int
    {
        return $this->approvals()->whereNotNull('verified_at')->count();
    }

    public function hasEnoughApprovals(): bool
    {
        return $this->verifiedApprovalsCount() >= $this->required_approvals;
    }

    public function otpPhoneMasked(): ?string
    {
        $approval = $this->relationLoaded('approvals')
            ? $this->approvals->sortByDesc('id')->first()
            : $this->approvals()->with('user')->latest('id')->first();

        $digits = preg_replace('/\D+/', '', (string) $approval?->user?->phone) ?: '';

        if ($digits === '') {
            return null;
        }

        return '••••'.substr($digits, -4);
    }
}
