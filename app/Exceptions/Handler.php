<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Throwable;

class Handler extends ExceptionHandler
{
    public function report(Throwable $exception)
    {
        \Log::error('Handler report chamado', [
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
        ]);
        if ($exception instanceof ApiException) {
            // Loga sempre em laravel.log, nunca duplicado
            $logData = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'status' => $exception->status,
                'reason' => $exception->reason,
                'channel' => $exception->channel,
            ];
            \Log::error('ApiException', $logData);
            return;
        }
        parent::report($exception);
    }
    /**
     * Register the exception handling callbacks for the application.
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof ApiException) {
            return response()->json($exception->toArray(), $exception->status);
        }
        return parent::render($request, $exception);
    }


}
