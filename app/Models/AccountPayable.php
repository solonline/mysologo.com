<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AccountPayable extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('account_payables');
    }

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public const RECURRENCE_WEEKLY = 'weekly';
    public const RECURRENCE_MONTHLY = 'monthly';
    public const RECURRENCE_QUARTERLY = 'quarterly';
    public const RECURRENCE_YEARLY = 'yearly';

    protected $fillable = [
        'supplier',
        'description',
        'category',
        'document_number',
        'issued_at',
        'due_date',
        'amount',
        'currency',
        'status',
        'paid_at',
        'notes',
        'is_recurring',
        'recurrence_interval',
        'recurrence_ends_at',
        'recurrence_occurrences',
        'recurrence_group_uuid',
        'occurrence_index',
        'occurrence_total',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'due_date' => 'date',
        'paid_at' => 'date',
        'amount' => 'decimal:2',
        'is_recurring' => 'boolean',
        'recurrence_ends_at' => 'date',
        'recurrence_occurrences' => 'integer',
        'occurrence_index' => 'integer',
        'occurrence_total' => 'integer',
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(AccountPayableAttachment::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (self $accountPayable): void {
            $accountPayable->attachments()->get()->each->delete();
        });
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PENDING)
            ->whereDate('due_date', '<', now()->toDateString());
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->due_date !== null
            && $this->due_date->isPast()
            && ! $this->due_date->isToday();
    }

    public function displayStatus(): string
    {
        if ($this->isOverdue()) {
            return 'overdue';
        }

        return $this->status;
    }

    public static function recurrenceIntervals(): array
    {
        return [
            self::RECURRENCE_WEEKLY,
            self::RECURRENCE_MONTHLY,
            self::RECURRENCE_QUARTERLY,
            self::RECURRENCE_YEARLY,
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_PAID,
            self::STATUS_CANCELLED,
        ];
    }

    public static function createSeries(array $data): array
    {
        if (empty($data['is_recurring'])) {
            return [self::create($data)];
        }

        $dates = self::recurrenceDates(
            CarbonImmutable::parse($data['due_date']),
            $data['recurrence_interval'],
            isset($data['recurrence_ends_at']) ? CarbonImmutable::parse($data['recurrence_ends_at']) : null,
            $data['recurrence_occurrences'] ?? null,
        );

        $groupUuid = (string) Str::uuid();
        $total = count($dates);
        $accounts = [];

        foreach ($dates as $index => $dueDate) {
            $accounts[] = self::create(array_merge($data, [
                'due_date' => $dueDate->toDateString(),
                'recurrence_group_uuid' => $groupUuid,
                'occurrence_index' => $index + 1,
                'occurrence_total' => $total,
            ]));
        }

        return $accounts;
    }

    public static function recurrenceDates(
        CarbonImmutable $startDate,
        string $interval,
        ?CarbonImmutable $endsAt,
        ?int $occurrences
    ): array {
        if ($endsAt === null && $occurrences === null) {
            throw new \InvalidArgumentException('Recurring accounts require an end date or occurrence limit.');
        }

        $dates = [];
        $current = $startDate;
        $index = 1;

        while (true) {
            if ($endsAt !== null && $current->greaterThan($endsAt)) {
                break;
            }

            if ($occurrences !== null && $index > $occurrences) {
                break;
            }

            $dates[] = $current;
            $current = match ($interval) {
                self::RECURRENCE_WEEKLY => $current->addWeek(),
                self::RECURRENCE_MONTHLY => $current->addMonthNoOverflow(),
                self::RECURRENCE_QUARTERLY => $current->addMonthsNoOverflow(3),
                self::RECURRENCE_YEARLY => $current->addYearNoOverflow(),
                default => throw new \InvalidArgumentException('Unsupported recurrence interval.'),
            };

            $index++;
        }

        return $dates;
    }
}
