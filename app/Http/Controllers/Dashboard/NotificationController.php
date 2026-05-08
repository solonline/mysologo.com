<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Lista as últimas notificações + contagem não lidas (JSON — chamado via fetch).
     */
    public function index(Request $request): JsonResponse
    {
        $user          = $request->user();
        $notifications = $this->notificationService->getRecentForUser($user, 15);
        $unreadCount   = $this->notificationService->getUnreadCountForUser($user);

        return response()->json([
            'notifications' => $notifications->map(fn($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'title'      => $n->title,
                'body'       => $n->body,
                'url'        => $n->url,
                'read_at'    => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->diffForHumans(),
            ]),
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Marca uma notificação como lida.
     */
    public function markRead(Request $request, UserNotification $notification): JsonResponse
    {
        abort_if($notification->user_id !== $request->user()->id, 403);

        $this->notificationService->markAsRead($notification);

        return response()->json(['ok' => true]);
    }

    /**
     * Marca todas as notificações do utilizador como lidas.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $this->notificationService->markAllReadForUser($request->user());

        return response()->json(['ok' => true]);
    }
}
