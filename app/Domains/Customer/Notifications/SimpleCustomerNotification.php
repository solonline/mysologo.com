<?php

namespace App\Domains\Customer\Notifications;

use App\Domains\Customer\Enums\CustomerNotificationType;
use App\Domains\Customer\Notifications\Channels\FcmNotificationChannel;
use App\Domains\Customer\Notifications\Channels\InAppNotificationChannel;
use App\Domains\Customer\Notifications\Contracts\CustomerAppNotification;

/**
 * Notificação ad-hoc (comando push:send, 3DS, etc.) sem God Service.
 */
class SimpleCustomerNotification implements CustomerAppNotification
{
    private ?int $persistedId = null;

    /**
     * @param  array<string, mixed>  $extra
     * @param  list<class-string>|null  $channelClasses
     */
    public function __construct(
        protected CustomerNotificationType|string $notificationType,
        protected string $notificationTitle,
        protected ?string $notificationBody = null,
        protected ?string $notificationResourceId = null,
        protected array $extra = [],
        protected ?array $channelClasses = null,
    ) {}

    public function type(): CustomerNotificationType|string
    {
        return $this->notificationType;
    }

    public function title(): string
    {
        return $this->notificationTitle;
    }

    public function body(): ?string
    {
        return $this->notificationBody;
    }

    public function resourceId(): ?string
    {
        return $this->notificationResourceId;
    }

    public function data(): array
    {
        return $this->extra;
    }

    public function channels(): array
    {
        return $this->channelClasses ?? [
            InAppNotificationChannel::class,
            FcmNotificationChannel::class,
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
