<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Services\StaffNotificationService;
use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected StaffNotificationService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $items = $this->notifications->getRecentForUser($user, 15);

        return response()->json([
            'notifications' => $items->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'url' => $n->url,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->diffForHumans(),
            ]),
            'unread_count' => $this->notifications->getUnreadCountForUser($user),
        ]);
    }

    public function markRead(Request $request, UserNotification $notification): JsonResponse
    {
        abort_if($notification->user_id !== $request->user()->id, 403);

        $this->notifications->markAsRead($notification);

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->notifications->markAllReadForUser($request->user());

        return response()->json(['ok' => true]);
    }
}
