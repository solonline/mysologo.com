<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalDocsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('docs.enabled', true)) {
            abort(404);
        }

        if (config('docs.internal_public', false)) {
            return $next($request);
        }

        if (auth()->check()) {
            return $next($request);
        }

        abort(403, 'Documentação interna restrita. Inicie sessão no backoffice ou active DOCS_INTERNAL_PUBLIC em desenvolvimento.');
    }
}
