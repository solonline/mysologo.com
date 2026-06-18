<?php

namespace App\Domains\Wallester\Controllers;

use App\Domains\Onboarding\Services\IntegrationAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WallesterWebhookController extends Controller
{
    public function __construct(
        protected IntegrationAccountService $integrationAccounts,
    ) {}

    /**
     * @hideFromApiDocs
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Received Wallester webhook', ['payload' => $payload]);

        $accountId = $payload['account_id'] ?? $payload['id'] ?? null;
        $status = $payload['status'] ?? $payload['account_status'] ?? null;
        $reason = $payload['reason'] ?? $payload['message'] ?? null;

        if ($accountId && $status) {
            $account = $this->integrationAccounts->findWallesterByExternalId((string) $accountId);

            if ($account) {
                $this->integrationAccounts->applyWallesterExternalStatus(
                    $account,
                    (string) $status,
                    $reason,
                    'wallester.webhook',
                );
            }
        }

        return response()->json(['success' => true]);
    }
}
