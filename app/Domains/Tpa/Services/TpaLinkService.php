<?php

namespace App\Domains\Tpa\Services;

use App\Domains\Customer\Contracts\CustomerRepositoryInterface;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\Tpa;
use Illuminate\Support\Facades\Hash;

class TpaLinkService
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
    ) {}

    /**
     * @return array{token: string, customer: Customer, tpa: Tpa}
     */
    public function link(string $email, string $password, string $tpaSerial): array
    {
        $customer = $this->customerRepository->findByEmail($email);

        if (! $customer || ! Hash::check($password, $customer->password)) {
            throw new ApiException(ErrorCode::INVALID_CREDENTIALS, 401, 'Invalid credentials.');
        }

        if ($customer->tpas()->where('serial', $tpaSerial)->exists()) {
            throw new ApiException(ErrorCode::TPA_ALREADY_LINKED, 422, 'This TPA is already linked to your account.');
        }

        $tpa = $customer->tpas()->create([
            'serial' => $tpaSerial,
            'status' => 'active',
        ]);

        $token = $customer->createToken('tpa_login_' . $tpa->serial)->plainTextToken;

        return [
            'token' => $token,
            'customer' => $customer,
            'tpa' => $tpa,
        ];
    }
}
