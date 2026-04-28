<?php
namespace App\Services;

use App\Enums\CustomerStatus;
use App\Enums\DocumentStatus;
use App\Events\CustomerApproved;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Contracts\CustomerRepositoryInterface;
use App\Contracts\DiditSessionRepositoryInterface;
use App\Contracts\RetailDocumentRepositoryInterface;
use App\Contracts\StripeAccountRepositoryInterface;
use App\Contracts\WallesterAccountRepositoryInterface;
use App\DTOs\CreateStripeAccountDTO;
use App\Models\Customer;
use App\Models\CustomerRetailDocument;
use App\Providers\DiditProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\UploadDocumentMediaJob;

class DiditService
{
    public function __construct(
        protected DiditProvider $diditProvider,
        protected DiditSessionRepositoryInterface $diditSessionRepository,
        protected RetailDocumentRepositoryInterface $retailDocumentRepository,
        protected CustomerRepositoryInterface $customerRepository,
        protected StripeAccountRepositoryInterface $stripeAccountRepository,
        protected StripeService $stripeService,
        protected WallesterService $walletService,
        protected WallesterAccountRepositoryInterface $wallesterAccountRepository,
    ) {}

    public function createSession(Customer $customer): array
    {
        $sessionData = [
            'workflow_id' => config('services.didit.workflow_id'),
            'customer_id' => $customer->id,
            // 'callback' => config('services.didit.callback_url'),
            'callback' => 'https://frontend.com/callback', //TODO: Definir URL de callback real
            'callback_method' => 'initiator',
            'language' => $customer->language ?? 'en',
        ];

        $result = $this->diditProvider->createSession($sessionData);

        if ($result['status'] === 'success' && isset($result['session']['session_id'])) {
            $body = $result['session'];
            $diditSession = $this->diditSessionRepository->upsertBySessionId(
                $customer,
                $body['session_id'],
                [
                    'workflow_id' => $body['workflow_id'] ?? null,
                    'session_url' => $body['url'] ?? null,
                    'status'      => $body['status'] ?? 'pending',
                    'callback'    => $sessionData['callback'],
                    'metadata'    => $body['metadata'] ?? null,
                ]
            );
            return [
                'status' => 'success',
                'message' => null,
                'session' => $diditSession->toArray(),
            ];
        }

        // Falha: retorna status e mensagem
        return [
            'status' => 'failed',
            'message' => $result['message'] ?? 'Erro ao criar sessão Didit',
            'session' => null,
        ];
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
                $diditSession = $this->diditSessionRepository->findBySessionId($sessionId);
                if (!$diditSession) {
                    Log::warning('Sessão Didit não encontrada', ['session_id' => $sessionId]);
                    continue;
                }

                // Criar ou recuperar documento
                $customerDocument = $this->retailDocumentRepository->firstOrCreateByDocumentNumber(
                    $documentNumber,
                    $diditSession->customer_id
                );

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
                        UploadDocumentMediaJob::dispatch($customerDocument->id, $field, $value);
                    }
                }

                $customer = $this->customerRepository->findById($customerDocument->customer_id);
                $customer->status = CustomerStatus::PENDING;

                // Salvar apenas se houve mudança
                if ($customerDocument->isDirty()) {
                    try {
                        $this->retailDocumentRepository->save($customerDocument);
                        Log::channel('diditDocumentJob')->info('Documento processado com sucesso', [
                            'customer_id'       => $customerDocument->customer_id,
                            'session_id'        => $sessionId,
                            'document_number'   => $customerDocument->document_number,
                            'fields_updated'    => array_keys($customerDocument->getChanges()),
                        ]);
                    } catch (\Throwable $e) {
                        Log::channel('diditDocumentJob')->error('Erro salvando documento', [
                            'customer_id'       => $customerDocument->customer_id ?? null,
                            'session_id'        => $sessionId,
                            'document_number'   => $documentNumber,
                            'exception'         => $e->getMessage(),
                            'trace'             => $e->getTraceAsString(),
                        ]);
                        throw $e; // Job poderá re-tentar
                    }
                   match($decision['status'] ?? null) {
                        'Approved'              => $customer->doc_status = DocumentStatus::APPROVED,
                        'Declined'              => $customer->doc_status = DocumentStatus::DECLINED,
                        'In Review'             => $customer->doc_status = DocumentStatus::ANALYSING,
                        'In Progress'           => $customer->doc_status = DocumentStatus::ANALYSING,
                        'Not Started'           => $customer->doc_status = DocumentStatus::PENDING,
                        default => null,
                    };

                    match($customer->doc_status) {
                        DocumentStatus::APPROVED    => $customer->status = CustomerStatus::ACTIVE,
                        DocumentStatus::PENDING     => $customer->status = CustomerStatus::PENDING,
                        DocumentStatus::DECLINED     => $customer->status = CustomerStatus::BLOCKED,
                        default => null,
                    };

                    if($customer->doc_status === DocumentStatus::APPROVED) {

                        // Create Stripe Customer
                        $stripeCustomer = $this->stripeService->createCustomer($customer);
                        $createStripeAccountDTO = new CreateStripeAccountDTO(
                            $customer->id,
                            $stripeCustomer['id']
                        );
                        $this->stripeAccountRepository->create($createStripeAccountDTO);

                        // Create Wallester Account
                        $wallesterAccount = $this->walletService->createAccount([
                            'name' => $customer->name,
                        ]);


                        // Persistir dados da conta Wallester
                        if ($wallesterAccount && isset($wallesterAccount['id'])) {
                            $this->wallesterAccountRepository->create([
                                'customer_id' => $customer->id,
                                'wallester_account_id' => $wallesterAccount['id'],
                                // Adicione outros campos necessários do retorno aqui
                            ]);
                        }
                    }
                    $this->customerRepository->save($customer);
                    if ($customer->status === CustomerStatus::ACTIVE) {
                        CustomerApproved::dispatch($customer);
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
