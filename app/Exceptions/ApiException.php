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
                    'file' => $this->getFile(),
                    'line' => $this->getLine(),
                    'status' => $status,
                    // Se houver previous, loga a mensagem original, senão a customizada
                    'message' => $previous ? $previous->getMessage() : $this->getMessage(),
                ];
                // Se for erro de validação (422), loga resposta da API se disponível
                if ($status === 422 && isset($reason) && is_array($reason)) {
                    $logData['api_response'] = $reason;
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
