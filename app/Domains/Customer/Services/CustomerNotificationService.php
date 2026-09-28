<?php

namespace App\Domains\Customer\Services;

use App\Domains\Customer\Enums\CustomerNotificationType;
use App\Domains\Customer\Events\CustomerNotificationCreated;
use App\Models\Customer;
use App\Models\CustomerNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerNotificationService
{
    public function paginate(Customer $customer, int $perPage = 20): LengthAwarePaginator
    {
        return CustomerNotification::where('customer_id', $customer->id)
            ->latest()
            ->paginate($perPage);
    }

    public function getUnreadCount(Customer $customer): int
    {
        return CustomerNotification::where('customer_id', $customer->id)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(CustomerNotification $notification): void
    {
        $notification->markAsRead();
    }

    public function markAllRead(Customer $customer): void
    {
        CustomerNotification::where('customer_id', $customer->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function notify(
        Customer $customer,
        CustomerNotificationType|string $type,
        string $title,
        ?string $body = null,
        array $data = [],
        string|int|null $resourceId = null,
    ): void {
        if ($type instanceof CustomerNotificationType) {
            $path = $type->path($resourceId);
            $payload = $data;

            if ($resourceId !== null && $resourceId !== '') {
                $payload['resource_id'] = (string) $resourceId;
            }

            if ($path !== null) {
                $payload['path'] = $path;
            }

            $typeValue = $type->value;
            $data = $payload;
        } else {
            $typeValue = $type;
        }

        $notification = CustomerNotification::create([
            'customer_id' => $customer->id,
            'type' => $typeValue,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        CustomerNotificationCreated::dispatch($notification);
    }
}
