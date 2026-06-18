<?php

namespace App\Domains\Customer\Controllers;

use App\Domains\Customer\Services\CustomerNotificationService;
use App\Http\Resources\NotificationResource;
use App\Models\CustomerNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    public function __construct(
        protected CustomerNotificationService $notifications,
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
        $customer = $request->user();
        $paginated = $this->notifications->paginate($customer);
        $unreadCount = $this->notifications->getUnreadCount($customer);

        return NotificationResource::collection($paginated)
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

        $this->notifications->markAsRead($notification);

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
        $this->notifications->markAllRead($request->user());

        return response()->json(['ok' => true]);
    }
}
