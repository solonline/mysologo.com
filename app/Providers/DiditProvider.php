<?php
namespace App\Providers;

use App\Clients\DIditClient;
use App\Exceptions\ApiException;

class DiditProvider
{
    protected DIditClient $client;

    public function __construct()
    {
        $this->client = new DIditClient();
    }

    public function createSession(array $data)
    {
        try {
            $response = $this->client->createSession($data);
        } catch (\Throwable $e) {
            \Log::warning('Erro de comunicação com Didit', [
                'exception' => $e,
                'data' => $data
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
            // Log detalhado só para o canal createDiditSession
            \Log::channel('createDiditSession')->warning('Falha ao criar sessão Didit', [
                'status' => $status,
                'body' => $body,
                'data' => $data,
                'internal_message' => $message
            ]);
            // Mensagem genérica para o consumidor da API
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

    public function createSessionResponse($data)
    {
        $response = $this->client->createSession($data);

        if ($response->status() !== 201) {
            return [
                'success' => false,
                'message' => 'Não foi possível criar a sessão Didit.',
                'detail' => $response->json(),
            ];
        }

        return $response->json();
    }

    public function getSessionStatus(string $sessionId): array
    {
        return $this->client->getSessionStatus($sessionId);
    }
}
