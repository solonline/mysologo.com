<?php

namespace App\Domains\Wise\Controllers;

use App\Domains\Wise\Jobs\ProcessWiseWebhookJob;
use App\Domains\Wise\Services\WiseWebhookSignature;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WiseWebhookController extends Controller
{
    public function __construct(
        protected WiseWebhookSignature $signature,
    ) {}

    /**
     * @hideFromApiDocs
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $valid = $this->signature->verify(
                $request->getContent(),
                (string) $request->header('X-Signature-SHA256', ''),
            );
        } catch (RuntimeException) {
            return response()->json([
                'message' => 'Wise webhook public key is not configured.',
            ], 401);
        }

        if (! $valid) {
            return response()->json([
                'message' => 'Invalid Wise webhook signature.',
            ], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all() ?: [];

        Log::channel('wise')->info('Received Wise webhook', [
            'event_type' => $payload['event_type'] ?? null,
            'delivery_id' => $request->header('X-Delivery-Id'),
        ]);

        ProcessWiseWebhookJob::dispatch(
            $payload,
            $request->header('X-Delivery-Id'),
        );

        return response()->json(['success' => true], 200);
    }
}
