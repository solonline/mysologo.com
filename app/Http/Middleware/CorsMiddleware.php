<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->withCorsHeaders(response('', 204));
        }

        /** @var Response $response */
        $response = $next($request);

        return $this->withCorsHeaders($response);
    }

    private function withCorsHeaders(Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', implode(', ', [
            'Content-Type',
            'Accept',
            'Authorization',
            'X-Requested-With',
            'Idempotency-Key',
            'X-Merchant-Key',
        ]));
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }
}
