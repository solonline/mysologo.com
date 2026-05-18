<?php

namespace App\Providers;

use App\Clients\SmsClient;

class SmsProvider
{
    public function __construct(protected SmsClient $client, protected string $from = 'Yoop')
    {
    }

    /**
     * Envia SMS usando o client Sms
     * @param array $to
     * @param string $message
     * @return array
     */
    public function sendSms(array $to, string $message): array
    {
        return $this->client->sendSms($to, $this->from, $message);
    }
}
