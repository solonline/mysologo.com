<?php

namespace App\Domains\Sms\Contracts;

interface SmsGatewayInterface
{
    /**
     * Envia SMS para um único destinatário.
     *
     * @return array{status: string, body: mixed, http_code: int}|array<int, array{status: string, body: mixed, http_code: int}>
     */
    public function send(string $to, string $message): array;

    /**
     * Envia SMS para múltiplos destinatários.
     *
     * @param  array<int, string>  $recipients
     * @return array<int, array{status: string, body: mixed, http_code: int}>
     */
    public function sendBulk(array $recipients, string $message): array;
}
