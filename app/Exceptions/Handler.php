<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        // ...existing code...
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof ApiException) {
            return response()->json([
                'success' => false,
                'reason' => $exception->reason,
                'message' => $exception->getMessage(),
                'status' => $exception->status,
            ], $exception->status);
        }
        return parent::render($request, $exception);
    }
}
