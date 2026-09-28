<?php

namespace App\Domains\Customer\Notifications;

use App\Domains\Customer\Enums\CustomerNotificationType;
use App\Domains\Customer\Notifications\Channels\EmailNotificationChannel;
use App\Domains\Customer\Notifications\Channels\FcmNotificationChannel;
use App\Domains\Customer\Notifications\Channels\InAppNotificationChannel;
use App\Domains\Customer\Notifications\Contracts\CustomerAppNotification;

class TopupImmediateNotification implements CustomerAppNotification
{
    private ?int $persistedId = null;

    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        protected string $amountLabel,
        protected int $transactionId,
        protected ?string $description = null,
        protected array $extra = [],
    ) {}

    public function type(): CustomerNotificationType
    {
        return CustomerNotificationType::TopupImmediate;
    }

    public function title(): string
    {
        return __('notifications.topup_immediate', [
            'amount' => $this->amountLabel,
        ]);
    }

    public function body(): ?string
    {
        return filled($this->description) ? (string) $this->description : null;
    }

    public function resourceId(): ?string
    {
        return (string) $this->transactionId;
    }

    public function data(): array
    {
        return $this->extra;
    }

    public function channels(): array
    {
        return [
            InAppNotificationChannel::class,
            FcmNotificationChannel::class,
            EmailNotificationChannel::class,
        ];
    }

    public function persistedId(): ?int
    {
        return $this->persistedId;
    }

    public function setPersistedId(int $id): void
    {
        $this->persistedId = $id;
    }
}
