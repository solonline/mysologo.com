<?php

namespace App\Domains\Kyc\Controllers;

use App\Domains\Kyc\Jobs\ProcessIdentityVerificationWebhook;
use App\Domains\Kyc\Services\IdentityVerificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DiditWebhookController extends Controller
{
    public function __construct(
        protected IdentityVerificationService $identityVerification,
    ) {}

    /**
     * @hideFromApiDocs
     */
    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('X-Signature-V2');
        // if (! $this->identityVerification->verifyWebhookSignature($request->getContent(), $signature)) {
        //     return response()->json(['error' => 'Invalid signature'], 401);
        // }

        Log::info('Received Didit webhook', ['payload' => $request->all()]);

        ProcessIdentityVerificationWebhook::dispatch($request->all())->onQueue('didit-webhooks');

        return response()->json(['success' => true]);
    }
}
