<?php

namespace App\Domains\Whatsapp\Jobs;

use App\Domains\Whatsapp\Exceptions\WhatsappException;
use App\Domains\Whatsapp\Services\WhatsappService;
use App\Domains\Whatsapp\Support\WhatsappLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendWhatsappTextJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 60, 120, 300, 600];

    public function __construct(
        public string $to,
        public string $text,
    ) {
        $this->onQueue('whatsapp');
    }

    public function handle(WhatsappService $whatsapp): void
    {
        $result = $whatsapp->sendText($this->to, $this->text);

        if ($result->success) {
            return;
        }

        WhatsappLogger::warning('whatsapp_text_job_failed', [
            'to' => $this->to,
            'http_code' => $result->httpCode,
            'error' => $result->error,
            'attempt' => $this->attempts(),
        ]);

        if ($result->isPermanentFailure()) {
            return;
        }

        throw new WhatsappException($result->error ?? 'Falha ao enviar WhatsApp.');
    }

    public function failed(?Throwable $exception): void
    {
        WhatsappLogger::error('whatsapp_text_job_exhausted', [
            'to' => $this->to,
            'message' => $exception?->getMessage(),
        ]);
    }
}
