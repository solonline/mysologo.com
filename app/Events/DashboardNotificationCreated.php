<?php

namespace App\Events;

use App\Models\UserNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DashboardNotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly UserNotification $notification
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('dashboard.' . $this->notification->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id'    => $this->notification->id,
            'type'  => $this->notification->type,
            'title' => $this->notification->title,
            'body'  => $this->notification->body,
            'url'   => $this->notification->url,
            'data'  => $this->notification->data,
            'created_at' => $this->notification->created_at->toIso8601String(),
        ];
    }
}
