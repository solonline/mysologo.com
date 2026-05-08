<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerNotification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * Lista as notificações do cliente autenticado (paginadas).
     */
    public function index(Request $request): JsonResponse
    {
        $customer      = $request->user();
        $notifications = $this->notificationService->paginateCustomerNotifications($customer);
        $unreadCount   = $this->notificationService->getUnreadCountForCustomer($customer);

        return response()->json([
            'data'         => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Marca uma notificação específica como lida.
     */
    public function markRead(Request $request, CustomerNotification $notification): JsonResponse
    {
        abort_if($notification->customer_id !== $request->user()->id, 403);

        $this->notificationService->markCustomerNotificationAsRead($notification);

        return response()->json(['ok' => true]);
    }

    /**
     * Marca todas as notificações do cliente como lidas.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $this->notificationService->markAllReadForCustomer($request->user());

        return response()->json(['ok' => true]);
    }
}
