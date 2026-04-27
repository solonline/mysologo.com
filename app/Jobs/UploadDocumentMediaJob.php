<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\CustomerRetailDocument;
use Illuminate\Support\Facades\Log;

class UploadDocumentMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $documentId;
    protected string $field;
    protected string $mediaUrl;

    /**
     * Create a new job instance.
     */
    public function __construct(int $documentId, string $field, string $mediaUrl)
    {
        $this->documentId = $documentId;
        $this->field = $field;
        $this->mediaUrl = $mediaUrl;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Exemplo: baixar a mídia e salvar em S3
        try {
            $document = CustomerRetailDocument::findOrFail($this->documentId);
            // Aqui você pode baixar a mídia de $this->mediaUrl e salvar no storage/S3
            // Exemplo fictício:
            // $contents = file_get_contents($this->mediaUrl);
            // Storage::disk('s3')->put("didit/{$document->id}/{$this->field}.jpg", $contents);
            Log::info('UploadDocumentMediaJob executado', [
                'document_id' => $this->documentId,
                'field' => $this->field,
                'media_url' => $this->mediaUrl,
            ]);
        } catch (\Throwable $e) {
            Log::error('Erro no UploadDocumentMediaJob', [
                'document_id' => $this->documentId,
                'field' => $this->field,
                'media_url' => $this->mediaUrl,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
