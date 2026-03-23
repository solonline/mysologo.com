<?php
namespace App\Services;

use App\Models\User;
use App\Providers\DiditProvider;
use Exception;

class DiditService
{
    protected DiditProvider $diditProvider;

    public function __construct()
    {
        $this->diditProvider = new DiditProvider();
    }

    public function createSession(User $user): array
    {
        $sessionData = [
            'workflow_id' => config('services.didit.workflow_id'),
            'user_id' => $user->id,
            'callback' => 'https://yourapp.com/api/didit/callback', //TODO definir callback
            'language' => $user->language ?? 'en',
        ];

        $diditSession = $this->diditProvider->createSession($sessionData);

        $user->diditSession()->firstOrCreate([
            'session_id'        => $diditSession['session_id']
        ], [
            'workflow_id'       => $diditSession['workflow_id'],
            'session_url'       => $diditSession['url'],
            'status'            => $diditSession['status'],
        ]);

        return $user->diditSession()->latest()->first()->toArray();
    }

    public function getSessionStatus(string $sessionId): array
    {
       $diditSession = $this->diditProvider->getSessionStatus($sessionId);

       return $diditSession;
    }
}
