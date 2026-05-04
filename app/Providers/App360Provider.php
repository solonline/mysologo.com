<?php

namespace App\Providers;

use App\Clients\App360Client;

class App360Provider
{
    public function __construct(protected App360Client $client, protected string $from = 'Yoop')
    {
    }

    /**
     * Envia SMS usando o client App360
     * @param array $to
     * @param string $message
     * @return array
     */
    public function sendSms(array $to, string $message): array
    {
        return $this->client->sendSms($to, $this->from, $message);
    }
}
