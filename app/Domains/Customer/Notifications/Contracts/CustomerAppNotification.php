<?php

namespace App\Domains\Customer\Notifications\Contracts;

use App\Domains\Customer\Enums\CustomerNotificationType;

interface CustomerAppNotification
{
    public function type(): CustomerNotificationType|string;

    public function title(): string;

    public function body(): ?string;

    public function resourceId(): ?string;

    /**
     * @return array<string, mixed>
     */
    public function data(): array;

    /**
     * @return list<class-string<NotificationChannel>>
     */
    public function channels(): array;

    public function persistedId(): ?int;

    public function setPersistedId(int $id): void;
}
