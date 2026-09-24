<?php

namespace App\Domains\Kyc\Contracts;

/**
 * Contrato agnóstico de provedor KYC (Didit hoje, outro amanhã).
 */
interface KycGatewayInterface
{
    /**
     * @return array{status: string, message: ?string, session: ?array}
     */
    public function createSession(array $data): array;

    public function getSessionStatus(string $sessionId): array;

    public function verifyWebhookSignature(string $payload, ?string $signature, array $headers = []): bool;
}
