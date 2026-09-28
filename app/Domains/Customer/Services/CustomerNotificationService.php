<?php

namespace App\Domains\Customer\Services;

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
}
