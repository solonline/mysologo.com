<?php

namespace App\Domains\Auth\Events;

use App\Models\Customer;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Disparado quando um novo utilizador é registado no sistema.
 *
 * O domínio Auth apenas autentica/regista; reações (e-mail de validação,
 * início do onboarding/KYC, etc.) ocorrem via listeners noutros domínios.
 */
class UserRegistered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Customer $customer,
        public ?string $validationUrl = null,
        public array $infoSystem = [],
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new \Illuminate\Broadcasting\PrivateChannel('channel-name'),
        ];
    }
}
