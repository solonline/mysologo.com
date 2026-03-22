<?php
namespace App\Services;

use App\Providers\DiditProvider;
use Exception;

class DiditService
{
    protected DiditProvider $diditProvider;

    public function __construct()
    {
        $this->diditProvider = new DiditProvider();
    }

    public function createSession(array $data)
    {
        return $this->diditProvider->createSession($data);
    }

    public function getSessionStatus(string $sessionId): array
    {
        return $this->diditProvider->getSessionStatus($sessionId);
    }
}
