<?php

namespace App\Http\Controllers\Webhooks;

use App\Clients\StripeClient;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessStripeWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function __construct(private readonly StripeClient $stripeClient) {}

    /**
     * @hideFromApiDocs
     */
    public function handle(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        Log::info('Recebido webhook Stripe', [
            'event' => $request->input('type'),
            'payload' => $payload,
        ]);
        if (! $sigHeader) {
            return response()->json(['error' => 'Missing Stripe-Signature header'], 400);
        }

        try {
            $event = $this->stripeClient->constructWebhookEvent($payload, $sigHeader);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook: assinatura inválida', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        ProcessStripeWebhook::dispatch($event->toArray())->onQueue('stripe-webhooks');

        return response()->json(['success' => true], 200);
    }
}
