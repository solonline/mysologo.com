<?php
namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    public string $reason;
    public int $status;
    public ?string $channel;

    public function __construct(string $reason, int $status, string $message = '', ?string $channel = null)
    {
        parent::__construct($message, $status);
        $this->reason = $reason;
        $this->status = $status;
        $this->channel = $channel;
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

