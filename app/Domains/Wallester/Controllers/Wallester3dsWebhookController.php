<?php

namespace App\Domains\Wallester\Controllers;

use App\Domains\Wallester\Services\Wallester3dsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class Wallester3dsWebhookController extends Controller
{
    public function __construct(
        protected Wallester3dsService $threeDsService,
    ) {}

    /**
     * @hideFromApiDocs
     */
    public function initiateAction(Request $request): JsonResponse
    {
        $xRequestId = (string) $request->header('X-Request-ID');

        try {
            $this->threeDsService->handleInitiateAction($xRequestId, $request->all());
        } catch (Throwable $e) {
            Log::channel('wallester')->error('wallester_3ds_initiate_failed', [
                'x_request_id' => $xRequestId,
                'message' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            if ($e instanceof RuntimeException) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            throw $e;
        }

        return response()->json([
            'X-Request-ID' => $xRequestId,
        ])->header('X-Request-ID', $xRequestId);
    }

    /**
     * @hideFromApiDocs
     */
    public function validate(Request $request): JsonResponse
    {
        $xRequestId = (string) $request->header('X-Request-ID');
        $data = $request->input('data', []);
        $wallesterRequestId = is_array($data) ? (string) ($data['request_id'] ?? '') : '';

        if ($wallesterRequestId === '') {
            return response()->json(['error' => 'Missing data.request_id'], 422);
        }

        $status = $this->threeDsService->resolveValidation($xRequestId, $wallesterRequestId);

        return response()->json([
            'X-Request-ID' => $xRequestId,
            'status' => $status->value,
        ])->header('X-Request-ID', $xRequestId);
    }
}
