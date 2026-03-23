<?php
namespace App\Services;

use App\Models\User;

class KycService
{
    public function __construct(protected DiditService $diditService)
    {

    }
    public function ensureValidSession(User $user)
    {
        $userDiditSession = $user->diditSession;

        if (!isset($userDiditSession) || $userDiditSession->status != 'Approved') {
            $diditService = $this->diditService;
            if (!isset($userDiditSession) || $userDiditSession->updated_at->addDays(7)->isPast()) {
                $diditService->createSession($user);
            } else {

                $action = match ($userDiditSession->status) {
                    'Declined', 'Abandoned', 'Expired' => function () use ($user, $diditService) {
                            $diditService->createSession($user);
                        },
                    default => null,
                };
                if ($action)
                    $action();
            }
        }
    }
}
