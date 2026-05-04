<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\CustomerRetailDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        try {
            $document = CustomerRetailDocument::findOrFail($this->documentId);

            // Baixar o conteúdo do arquivo
            $contents = @file_get_contents($this->mediaUrl);
            if ($contents === false) {
                throw new \Exception('Não foi possível baixar o arquivo da URL: ' . $this->mediaUrl);
            }

            // Definir extensão baseada na URL ou default
            $extension = pathinfo(parse_url($this->mediaUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filename = "customer/{$document->customer_id}/didit/{$document->id}/{$this->field}.{$extension}";

            // Salvar no storage local (pode trocar para 's3' se necessário)
            Storage::disk('public')->put($filename, $contents);

            // Atualizar o campo na model
            $document->{$this->field} = $filename;
            $document->save();

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
