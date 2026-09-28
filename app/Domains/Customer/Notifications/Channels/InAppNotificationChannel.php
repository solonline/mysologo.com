<?php

namespace App\Domains\Customer\Notifications\Channels;

use App\Domains\Customer\Enums\CustomerNotificationType;
use App\Domains\Customer\Notifications\Contracts\CustomerAppNotification;
use App\Domains\Customer\Notifications\Contracts\NotificationChannel;
use App\Models\Customer;
use App\Models\CustomerNotification;

class InAppNotificationChannel implements NotificationChannel
{
    public function send(Customer $customer, CustomerAppNotification $notification): void
    {
        $type = $notification->type();
        $typeValue = $type instanceof CustomerNotificationType ? $type->value : (string) $type;
        $resourceId = $notification->resourceId();

        $payload = $notification->data();
        $payload['type'] = $typeValue;

        if ($resourceId !== null && $resourceId !== '') {
            $payload['resource_id'] = (string) $resourceId;
        }

        $path = $type instanceof CustomerNotificationType
            ? $type->path($resourceId)
            : ($payload['path'] ?? null);

        if (is_string($path) && $path !== '') {
            $payload['path'] = $path;
        }

        $row = CustomerNotification::query()->create([
            'customer_id' => $customer->id,
            'type' => $typeValue,
            'title' => $notification->title(),
            'body' => $notification->body(),
            'data' => $payload,
        ]);

        $payload['notification_id'] = (string) $row->id;
        $row->update(['data' => $payload]);

        $notification->setPersistedId((int) $row->id);
    }
}
