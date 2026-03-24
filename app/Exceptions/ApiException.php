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
        parent::__construct($message);
        $this->reason = $reason;
        $this->status = $status;
        $this->channel = $channel;
    }
}
