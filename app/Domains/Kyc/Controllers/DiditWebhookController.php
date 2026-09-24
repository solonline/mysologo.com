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
        $rawBody = $request->getContent();
        $verified = $this->identityVerification->verifyWebhookSignature(
            $rawBody,
            $request->header('X-Signature-V2'),
            [
                'signature' => $request->header('X-Signature'),
                'signature_simple' => $request->header('X-Signature-Simple'),
                'timestamp' => $request->header('X-Timestamp'),
            ],
        );

        if (! $verified) {
            Log::channel('diditDocumentJob')->warning('Didit webhook recusado: assinatura inválida', [
                'has_signature_v2' => $request->header('X-Signature-V2') !== null,
                'has_signature' => $request->header('X-Signature') !== null,
                'has_signature_simple' => $request->header('X-Signature-Simple') !== null,
                'has_timestamp' => $request->header('X-Timestamp') !== null,
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        Log::channel('diditDocumentJob')->info('Received Didit webhook', ['payload' => $request->all()]);

        ProcessIdentityVerificationWebhook::dispatch($request->all())->onQueue('didit-webhooks');

        return response()->json(['success' => true]);
    }
}
