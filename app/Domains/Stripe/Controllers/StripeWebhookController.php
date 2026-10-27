<?php

namespace App\Domains\Stripe\Controllers;

use App\Domains\Stripe\Clients\StripeHttpClient;
use App\Domains\Stripe\Jobs\ProcessStripeWebhook;
use App\Domains\Stripe\Services\StripeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;

class StripeWebhookController extends Controller
{
    public function __construct(private readonly StripeHttpClient $stripeClient) {}

    /**
     * @hideFromApiDocs
     */
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        Log::channel('stripe')->info('Recebido webhook Stripe', [
            'event' => $request->input('type'),
            'payload' => $payload,
        ]);
        if (! $sigHeader) {
            return response()->json(['error' => 'Missing Stripe-Signature header'], 400);
        }

        try {
            $event = $this->stripeClient->constructWebhookEvent($payload, $sigHeader);
        } catch (SignatureVerificationException $e) {
            Log::channel('stripe')->warning('Stripe webhook: assinatura inválida', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $eventPayload = $event->toArray();

        if (StripeService::isTestModeEvent($eventPayload)) {
            Log::channel('stripe')->info('Stripe webhook de teste ignorado', [
                'event' => $eventPayload['id'] ?? null,
                'type' => $eventPayload['type'] ?? null,
            ]);

            return response()->json(['success' => true], 200);
        }

        ProcessStripeWebhook::dispatch($eventPayload)->onQueue('stripe-webhooks');

        return response()->json(['success' => true], 200);
    }
}
