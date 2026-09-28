<?php

namespace App\Domains\Push\Jobs;

use App\Domains\Push\Clients\FcmClient;
use App\Models\CustomerDevice;
use App\Models\CustomerNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendCustomerPushJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $notificationId,
    ) {}

    public function handle(FcmClient $fcm): void
    {
        $notification = CustomerNotification::query()->find($this->notificationId);

        if ($notification === null) {
            return;
        }

        $devices = CustomerDevice::query()
            ->where('customer_id', $notification->customer_id)
            ->where('push_enabled', true)
            ->whereNotNull('push_token')
            ->get();

        $payload = is_array($notification->data) ? $notification->data : [];

        $data = [
            'type' => (string) $notification->type,
            'notification_id' => (string) $notification->id,
        ];

        $resourceId = $payload['resource_id'] ?? null;
        $path = $payload['path'] ?? null;

        if ($resourceId !== null && $resourceId !== '') {
            $data['resource_id'] = (string) $resourceId;
        }

        if ($path !== null && $path !== '') {
            $data['path'] = (string) $path;
        }

        $challengeId = $payload['challenge_id'] ?? null;

        if (! isset($data['resource_id']) && $challengeId !== null && $challengeId !== '') {
            $data['challenge_id'] = (string) $challengeId;
        }

        foreach ($devices as $device) {
            $result = $fcm->send(
                (string) $device->push_token,
                (string) $notification->title,
                $notification->body,
                $data,
            );

            if ($result->invalidToken) {
                $device->fill([
                    'push_token' => null,
                    'push_enabled' => false,
                ]);
                $device->save();
            }
        }
    }
}
