<?php
namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    public string $reason;
    public int $status;
    public ?string $channel;

    public function __construct(string $reason, int $status, string $message = '', ?string $channel = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);
        $this->reason = $reason;
        $this->status = $status;
        $this->channel = $channel;

        // Log automático se canal informado
        if ($channel) {
            try {
                $logData = [
                    'reason'  => $reason,
                    'status'  => $status,
                    'message' => $previous ? $previous->getMessage() : $this->getMessage(),
                    'file'    => $this->getFile(),
                    'line'    => $this->getLine(),
                ];

                // Contexto extra para erros do Stripe SDK
                if ($previous instanceof \Stripe\Exception\ApiErrorException) {
                    $stripeError            = $previous->getError();
                    $logData['stripe']      = array_filter([
                        'request_id'   => $previous->getRequestId(),
                        'http_status'  => $previous->getHttpStatus(),
                        'code'         => $stripeError?->code,
                        'decline_code' => $stripeError?->decline_code,
                        'param'        => $stripeError?->param,
                        'type'         => $stripeError?->type,
                    ]);
                }

                \Log::channel($channel)->error('ApiException', $logData);
            } catch (\Throwable $e) {
                // fallback: não interrompe fluxo se canal não existir
            }
        }
    }

    public function toArray()
    {
        $env = env('APP_ENV', 'production');
        if ($env === 'production') {
            return [
                'message' => $this->getMessage(),
                'status' => $this->status,
                'channel' => $this->channel,
            ];
        }
        // local/dev: retorna tudo
        return [
            'message' => $this->getMessage(),
            'status' => $this->status,
            'reason' => $this->reason,
            'channel' => $this->channel,
        ];
    }
}
