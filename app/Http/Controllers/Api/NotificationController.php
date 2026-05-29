<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\CustomerNotification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * List Notifications
     *
     * Returns the authenticated customer's notifications (most recent first) and the total unread count.
     *
     * @tags Notifications
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $customer      = $request->user();
        $notifications = $this->notificationService->paginateCustomerNotifications($customer);
        $unreadCount   = $this->notificationService->getUnreadCountForCustomer($customer);

        return NotificationResource::collection($notifications)
            ->additional(['unread_count' => $unreadCount]);
    }

    /**
     * Mark Notification as Read
     *
     * Marks a single notification as read. Returns 403 if the notification does not belong to the authenticated customer.
     *
     * @tags Notifications
     */
    public function markRead(Request $request, CustomerNotification $notification): JsonResponse
    {
        abort_if($notification->customer_id !== $request->user()->id, 403);

        $this->notificationService->markCustomerNotificationAsRead($notification);

        return response()->json(['ok' => true]);
    }

    /**
     * Mark All Notifications as Read
     *
     * Marks all of the authenticated customer's notifications as read.
     *
     * @tags Notifications
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $this->notificationService->markAllReadForCustomer($request->user());

        return response()->json(['ok' => true]);
    }
}
