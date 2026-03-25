<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDiditWebohook;
use App\Services\DiditService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Request;

class DiditWebhookController extends Controller
{
    protected DiditService $diditService;

    public function __construct(DiditService $diditService)
    {
        $this->diditService = $diditService;
    }

    /**
     * @hideFromApiDocs
     */
    public function handle(Request $request)
    {
        // Validar assinatura X-Signature-V2
        $signature = $request->header('X-Signature-V2');
        if (!$this->diditService->verifySignature($request->getContent(), $signature)) {
            return response()->json(['error' => 'Invalid signature', 'signature' => $signature], 401);
        }

        // Enviar para processamento assíncrono
        ProcessDiditWebohook::dispatch($request->all())->onQueue('didit-webhooks');

        // Resposta rápida para Didit
        return response()->json(['success' => true]);
    }
}
