<?php

namespace App\Domains\Kyc\Gateways;

use App\Domains\Kyc\Clients\DiditHttpClient;
use App\Domains\Kyc\Contracts\KycGatewayInterface;
use App\Domains\Settings\Services\DiditSettingsService;
use Illuminate\Support\Facades\Log;

class DiditKycGateway implements KycGatewayInterface
{
    public function __construct(
        protected DiditHttpClient $client,
        protected DiditSettingsService $diditSettings,
    ) {}

    public function createSession(array $data): array
    {
        try {
            $response = $this->client->createSession($data);
        } catch (\Throwable $e) {
            Log::warning('Erro de comunicação com Didit', [
                'exception' => $e,
                'data' => $data,
            ]);

            return [
                'status' => 'failed',
                'message' => 'Erro de comunicação com Didit',
                'session' => null,
            ];
        }

        $status = $response->status();
        $body = $response->json();

        if ($status !== 201) {
            $message = $this->mapDiditError($status, $body);
            Log::channel('createDiditSession')->warning('Falha ao criar sessão Didit', [
                'status' => $status,
                'body' => $body,
                'data' => $data,
                'internal_message' => $message,
            ]);

            return [
                'status' => 'failed',
                'message' => 'Erro ao iniciar verificação. Tente novamente mais tarde.',
                'session' => null,
            ];
        }

        return [
            'status' => 'success',
            'message' => null,
            'session' => $body,
        ];
    }

    public function getSessionStatus(string $sessionId): array
    {
        return $this->client->getSessionStatus($sessionId);
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        if (! $signature) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->diditSettings->webhookSecret());

        return hash_equals($expectedSignature, $signature);
    }

    protected function mapDiditError(int $status, ?array $body): string
    {
        return match ($status) {
            400 => 'Requisição inválida para o serviço de verificação.',
            401, 403 => 'Falha de autenticação com o serviço de verificação.',
            409 => 'Já existe uma sessão de verificação em andamento.',
            422 => $body['message'] ?? 'Dados inválidos enviados para verificação.',
            429 => 'Limite de requisições atingido. Tente novamente mais tarde.',
            default => 'Erro ao iniciar verificação. Tente novamente mais tarde.',
        };
    }
}
