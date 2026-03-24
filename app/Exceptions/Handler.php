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
            if ($exception->channel) {
                Log::channel($exception->channel)->error($exception->getMessage(), [
                    'reason' => $exception->reason,
                    'status' => $exception->status,
                ]);
            }
            return response()->json([
                'error' => $exception->reason,
                'message' => $exception->getMessage(),
            ], $exception->status);
        }
        return parent::render($request, $exception);
    }
}
