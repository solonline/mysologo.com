<?php

namespace App\Domains\Backoffice\Services;

use App\Domains\Backoffice\Events\DashboardNotificationCreated;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Collection;

class StaffNotificationService
{
    /**
     * Notifica utilizadores do backoffice com a permissão indicada.
     */
    public function notifyUsersWithPermission(
        string $type,
        string $title,
        string $permission,
        ?string $body = null,
        ?string $url = null,
        array $data = [],
    ): void {
        $users = User::permission($permission)->get();

        foreach ($users as $user) {
            $notification = UserNotification::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'permission_required' => $permission,
                'url' => $url,
            ]);

            broadcast(new DashboardNotificationCreated($notification))->toOthers();
        }
    }

    public function getUnreadCountForUser(User $user): int
    {
        return UserNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    /** @return Collection<int, UserNotification> */
    public function getRecentForUser(User $user, int $limit = 10): Collection
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
}
