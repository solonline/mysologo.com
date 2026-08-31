<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyRequiredMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle($request, Closure $next)
    {
        if (! $request->header('Idempotency-Key')) {
            return response()->json([
                'error' => 'Idempotency-Key required',
            ], 400);
        }

        $key = $request->header('Idempotency-Key');

        $endpoint = $request->path();

        $hash = hash('sha256', json_encode($request->all()));

        $existing = IdempotencyKey::where('key', $key)
            ->where('endpoint', $endpoint)
            ->first();

        if ($existing) {
            // Payload diferente = erro
            if ($existing->request_hash !== $hash) {
                return response()->json([
                    'error' => 'Idempotency key reuse with different payload',
                ], 409);
            }

            // Retorna resposta salva
            return response()->json(
                json_decode($existing->response, true),
                $existing->status_code
            );
        }

        $response = $next($request);

        if ($response instanceof Response && $response->isSuccessful()) {
            IdempotencyKey::create([
                'key' => $key,
                'endpoint' => $endpoint,
                'request_hash' => $hash,
                'response' => $response->getContent(),
                'status_code' => $response->getStatusCode(),
            ]);
        }

        return $response;
    }
}
