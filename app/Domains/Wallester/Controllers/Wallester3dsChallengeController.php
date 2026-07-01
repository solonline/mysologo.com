<?php

namespace App\Domains\Wallester\Controllers;

use App\Domains\Wallester\Services\Wallester3dsService;
use App\Http\Controllers\Controller;
use App\Models\Wallester3dsChallenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class Wallester3dsChallengeController extends Controller
{
    public function __construct(
        protected Wallester3dsService $threeDsService,
    ) {}

    /**
     * @hideFromApiDocs
     */
    public function pending(Request $request): JsonResponse
    {
        $challenges = $this->threeDsService->listPendingForCustomer($request->user());

        return response()->json([
            'data' => $challenges->map(fn (Wallester3dsChallenge $challenge) => $this->transformChallenge($challenge))->values(),
        ]);
    }

    /**
     * @hideFromApiDocs
     */
    public function approve(Request $request, Wallester3dsChallenge $challenge): JsonResponse
    {
        return $this->resolve($request, $challenge, 'approve');
    }

    /**
     * @hideFromApiDocs
     */
    public function reject(Request $request, Wallester3dsChallenge $challenge): JsonResponse
    {
        return $this->resolve($request, $challenge, 'reject');
    }

    private function resolve(Request $request, Wallester3dsChallenge $challenge, string $action): JsonResponse
    {
        try {
            $updated = $action === 'approve'
                ? $this->threeDsService->approve($challenge, $request->user())
                : $this->threeDsService->reject($challenge, $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => $this->transformChallenge($updated),
        ]);
    }

    /** @return array<string, mixed> */
    private function transformChallenge(Wallester3dsChallenge $challenge): array
    {
        return [
            'id' => $challenge->id,
            'wallester_request_id' => $challenge->wallester_request_id,
            'status' => $challenge->status->value,
            'merchant' => $challenge->merchant,
            'transaction' => $challenge->transaction,
            'card' => $challenge->card,
            'expires_at' => $challenge->expires_at?->toIso8601String(),
            'resolved_at' => $challenge->resolved_at?->toIso8601String(),
        ];
    }
}
