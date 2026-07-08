<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Actions\RetryAwaitingWallesterTopupsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class OperationsController extends Controller
{
    public function __construct(
        protected RetryAwaitingWallesterTopupsAction $retryAwaitingTopups,
    ) {}

    public function retryWallesterTopups(): RedirectResponse
    {
        $result = $this->retryAwaitingTopups->execute();

        return redirect()
            ->back(fallback: route('dashboard'))
            ->with('success', sprintf(
                'Main sincronizada (%d conta(s)). %d top-up(s) reenviado(s), %d ignorado(s).',
                $result['synced_main'],
                $result['dispatched'],
                $result['skipped'],
            ));
    }
}
