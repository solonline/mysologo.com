<?php

namespace App\Domains\Sms\Gateways;

use App\Domains\Sms\Clients\SmsHttpClient;
use App\Domains\Sms\Contracts\SmsGatewayInterface;

class SmsGateway implements SmsGatewayInterface
{
    public function __construct(
        protected SmsHttpClient $client,
        protected string $from = 'Yoop',
    ) {}

    public function send(string $to, string $message): array
    {
        return $this->client->sendSms([$to], $this->from, $message);
    }

    public function sendBulk(array $recipients, string $message): array
    {
        $result = $this->client->sendSms($recipients, $this->from, $message);

        return isset($result['status']) ? [$result] : $result;
    }
}
