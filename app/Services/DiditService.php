<?php
namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDiditSession;
use App\Models\CustomerRetailDocument;
use App\Providers\DiditProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiditService
{
    protected DiditProvider $diditProvider;

    public function __construct()
    {
        $this->diditProvider = new DiditProvider();
    }

    public function createSession(Customer $customer): array
    {
        $sessionData = [
            'workflow_id' => config('services.didit.workflow_id'),
            'customer_id' => $customer->id,
            'callback' => 'https://yourapp.com/api/didit/callback', //TODO definir callback
            'language' => $customer->language ?? 'en',
        ];

        $diditSession = $this->diditProvider->createSession($sessionData);

        $customer->diditSessions()->firstOrCreate([
            'session_id' => $diditSession['session_id']
        ], [
            'workflow_id' => $diditSession['workflow_id'],
            'session_url' => $diditSession['url'],
            'status' => $diditSession['status'],
        ]);

        return $customer->diditSessions()->latest()->first()->toArray();
    }

    public function getSessionStatus(string $sessionId): array
    {
        $diditSession = $this->diditProvider->getSessionStatus($sessionId);

        return $diditSession;
    }


    /**
     * Processa eventos de data.updated (documentação enviada/alterada)
     *
     * @param array $payload
     * @return void
     */
    public function handleData(array $payload): void
    {
        $sessionId = $payload['session_id'] ?? null;
        $decision = $payload['decision'] ?? [];
        $idVerificationsArray = $decision['id_verifications'] ?? [];

        if (!$sessionId || empty($idVerificationsArray)) {
            Log::warning('Webhook data.updated inválido', ['payload' => $payload]);
            return;
        }

        DB::transaction(function () use ($payload, $decision, $idVerificationsArray, $sessionId) {

            foreach ($idVerificationsArray as $idVerifications) {

                $documentNumber = $idVerifications['document_number'] ?? null;
                if (!$documentNumber) {
                    Log::warning('Documento sem número ignorado', ['session_id' => $sessionId]);
                    continue;
                }

                // Buscar a sessão Didit
                $diditSession = CustomerDiditSession::where('session_id', $sessionId)->first();
                if (!$diditSession) {
                    Log::warning('Sessão Didit não encontrada', ['session_id' => $sessionId]);
                    continue;
                }

                // Criar ou recuperar documento
                $customerDocument = CustomerRetailDocument::firstOrCreate([
                    'document_number' => $documentNumber
                ], [
                    'customer_id' => $diditSession->customer_id,
                ]);

                $fillable = (new CustomerRetailDocument())->getFillable();

                // Combina os dados do documento, decisão e payload
                $data = array_merge($idVerifications, $decision, $payload);

                // Filtra apenas os campos presentes em $fillable
                $filteredData = array_intersect_key($data, array_flip($fillable));

                // Campos de mídia que devem gerar jobs de upload
                $mediaFields = [
                    'front_image',
                    'back_image',
                    'full_front_image',
                    'full_back_image',
                    'portrait_image',
                    'front_video',
                    'back_video',
                    'back_image_camera_front',
                    'front_image_camera_front',
                    'back_image_camera_front_face_match_score',
                    'front_image_camera_front_face_match_score'
                ];

                // Preencher campos do fillable
                foreach ($filteredData as $field => $value) {
                    $customerDocument->{$field} = $value;

                    if (in_array($field, $mediaFields) && !empty($value)) {
                        //TODO: Disparar job de upload para S3/CDN
                        //UploadDocumentMediaJob::dispatch($customerDocument->id, $field, $value);
                    }
                }

                // Salvar apenas se houve mudança
                if ($customerDocument->isDirty()) {
                    try {
                        $customerDocument->save();
                        Log::channel('diditDocumentJob')->info('Documento processado com sucesso', [
                            'customer_id' => $customerDocument->customer_id,
                            'session_id' => $sessionId,
                            'document_number' => $customerDocument->document_number,
                            'fields_updated' => array_keys($customerDocument->getChanges()),
                        ]);
                    } catch (\Throwable $e) {
                        Log::channel('diditDocumentJob')->error('Erro salvando documento', [
                            'customer_id' => $customerDocument->customer_id ?? null,
                            'session_id' => $sessionId,
                            'document_number' => $documentNumber,
                            'exception' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        throw $e; // Job poderá re-tentar
                    }
                }
            }
        });
    }

    public function verifySignature(string $payload, ?string $signature): bool
    {
        if (!$signature)
            return false;

        $expectedSignature = hash_hmac('sha256', $payload, config('services.didit.webhook_secret'));

        return hash_equals($expectedSignature, $signature);
    }
}
