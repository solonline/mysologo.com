<?php

namespace App\Services;

use App\Events\DashboardNotificationCreated;
use App\Models\Customer;
use App\Models\CustomerNotification;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationService
{
    /**
     * Notifica todos os utilizadores do backoffice que tenham a permissão indicada.
     * Faz broadcast via Reverb para cada utilizador.
     */
    public function notifyUsers(
        string $type,
        string $title,
        string $permission,
        ?string $body = null,
        ?string $url = null,
        array $data = []
    ): void {
        $users = User::permission($permission)->get();

        foreach ($users as $user) {
            $notification = UserNotification::create([
                'user_id'             => $user->id,
                'type'                => $type,
                'title'               => $title,
                'body'                => $body,
                'data'                => $data,
                'permission_required' => $permission,
                'url'                 => $url,
            ]);

            broadcast(new DashboardNotificationCreated($notification))->toOthers();
        }
    }

    /**
     * Notifica um cliente específico (in-app).
     */
    public function notifyCustomer(
        Customer $customer,
        string $type,
        string $title,
        ?string $body = null,
        array $data = []
    ): void {
        CustomerNotification::create([
            'customer_id' => $customer->id,
            'type'        => $type,
            'title'       => $title,
            'body'        => $body,
            'data'        => $data,
        ]);
    }

    // ──────────────────────────────────────────────
    // Helpers para o Dashboard controller
    // ──────────────────────────────────────────────

    public function getUnreadCountForUser(User $user): int
    {
        return UserNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function getRecentForUser(User $user, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return UserNotification::where('user_id', $user->id)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function markAsRead(UserNotification $notification): void
    {
        $notification->markAsRead();
    }

    public function markAllReadForUser(User $user): void
    {
        UserNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    // ──────────────────────────────────────────────
    // Helpers para a API (customer)
    // ──────────────────────────────────────────────

    public function paginateCustomerNotifications(Customer $customer, int $perPage = 20): LengthAwarePaginator
    {
        return CustomerNotification::where('customer_id', $customer->id)
            ->latest()
            ->paginate($perPage);
    }

    public function getUnreadCountForCustomer(Customer $customer): int
    {
        return CustomerNotification::where('customer_id', $customer->id)
            ->whereNull('read_at')
            ->count();
    }

    public function markCustomerNotificationAsRead(CustomerNotification $notification): void
    {
        $notification->markAsRead();
    }

    public function markAllReadForCustomer(Customer $customer): void
    {
        CustomerNotification::where('customer_id', $customer->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
