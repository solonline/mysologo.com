<?php

namespace App\Domains\Kyc\Jobs;

use App\Models\CustomerRetailDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadDocumentMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected int $documentId,
        protected string $field,
        protected string $mediaUrl,
    ) {}

    public function handle(): void
    {
        try {
            $document = CustomerRetailDocument::query()
                ->with('customer')
                ->findOrFail($this->documentId);

            $contents = @file_get_contents($this->mediaUrl);
            if ($contents === false) {
                throw new \Exception('Não foi possível baixar o arquivo da URL: '.$this->mediaUrl);
            }

            $extension = pathinfo(parse_url($this->mediaUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = "customer/{$document->customer_id}/didit/{$document->id}/{$this->field}.{$extension}";

            Storage::disk('public')->put($filename, $contents);

            $document->{$this->field} = $filename;
            $document->save();

            if ($this->field === 'portrait_image' && $document->customer) {
                $document->customer->avatar = $filename;
                $document->customer->save();
            }

            Log::info('UploadDocumentMediaJob executado', [
                'document_id' => $this->documentId,
                'field' => $this->field,
                'media_url' => $this->mediaUrl,
                'saved_path' => $filename,
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
