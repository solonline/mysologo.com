<?php

namespace App\Jobs;

use App\Services\DiditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDiditWebohook implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(protected array $payload)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(DiditService $diditService): void
    {
        $diditService->handleData($this->payload);
    }
}
