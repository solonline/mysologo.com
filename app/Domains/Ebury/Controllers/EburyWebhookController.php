<?php

namespace App\Domains\Ebury\Controllers;

use App\Domains\Onboarding\Services\IntegrationAccountService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EburyWebhookController extends Controller
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

        Log::info('Received Ebury webhook', ['payload' => $payload]);

        $accountId = $payload['account_id'] ?? $payload['id'] ?? $payload['ebury_account_id'] ?? null;
        $status = $payload['status'] ?? $payload['account_status'] ?? null;
        $reason = $payload['reason'] ?? $payload['message'] ?? $payload['rejection_reason'] ?? null;

        if (! $accountId || ! $status) {
            return response()->json(['success' => true]);
        }

        $account = $this->integrationAccounts->findEburyByExternalId((string) $accountId);

        if (! $account) {
            Log::warning('Ebury webhook: account not found', ['account_id' => $accountId]);

            return response()->json(['success' => true]);
        }

        $this->integrationAccounts->applyEburyExternalStatus(
            $account,
            (string) $status,
            $reason,
            'ebury.webhook',
        );

        return response()->json(['success' => true]);
    }
}
