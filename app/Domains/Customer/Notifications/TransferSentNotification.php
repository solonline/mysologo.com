<?php

namespace App\Domains\Customer\Notifications;

use App\Domains\Customer\Enums\CustomerNotificationType;
use App\Domains\Customer\Notifications\Channels\EmailNotificationChannel;
use App\Domains\Customer\Notifications\Channels\InAppNotificationChannel;
use App\Domains\Customer\Notifications\Contracts\CustomerAppNotification;

class TransferSentNotification implements CustomerAppNotification
{
    private ?int $persistedId = null;

    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        protected string $recipient,
        protected string $amountLabel,
        protected ?string $description,
        protected int $movementId,
        protected array $extra = [],
    ) {}

    public function type(): CustomerNotificationType
    {
        return CustomerNotificationType::TransferSent;
    }

    public function title(): string
    {
        return __('notifications.transfer_sent', [
            'recipient' => $this->recipient,
            'amount' => $this->amountLabel,
        ]);
    }

    public function body(): ?string
    {
        return filled($this->description) ? (string) $this->description : null;
    }

    public function resourceId(): ?string
    {
        return (string) $this->movementId;
    }

    public function data(): array
    {
        return $this->extra;
    }

    public function channels(): array
    {
        return [
            InAppNotificationChannel::class,
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
