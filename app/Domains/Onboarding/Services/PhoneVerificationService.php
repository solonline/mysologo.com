<?php

namespace App\Domains\Onboarding\Services;

use App\Domains\Onboarding\Contracts\PhoneVerificationRepositoryInterface;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Onboarding\Services\CustomerLifecycleService;
use App\Domains\Sms\Contracts\SmsGatewayInterface;
use App\Models\Customer;
use App\Models\PhoneVerification;

class PhoneVerificationService
{
    public function __construct(
        protected PhoneVerificationRepositoryInterface $repository,
        protected SmsGatewayInterface $smsGateway,
        protected CustomerLifecycleService $lifecycle,
    ) {}

    public function createOrUpdate(Customer $customer, string $phone, string $code): PhoneVerification
    {
        return $this->repository->createOrUpdate($customer, $phone, $code);
    }

    public function verify(Customer $customer, string $phone, string $code): bool
    {
        $verified = $this->repository->verify($customer, $phone, $code);

        if ($verified && $this->lifecycle->current($customer) === CustomerLifecycleStatus::PENDING_PHONE_VERIFICATION) {
            $this->lifecycle->transitionTo($customer, CustomerLifecycleStatus::PENDING_IDENTITY_VERIFICATION);
        }

        return $verified;
    }

    public function isVerified(Customer $customer, string $phone): bool
    {
        return $this->repository->isVerified($customer, $phone);
    }

    /**
     * Gera código, persiste e envia SMS de verificação.
     *
     * @return bool true se SMS enviado, false se já estava verificado
     */
    public function sendSms(Customer $customer, string $phone): bool
    {
        if ($this->isVerified($customer, $phone)) {
            return false;
        }

        $code = random_int(100000, 999999);
        $this->createOrUpdate($customer, $phone, (string) $code);

        $message = "Olá! Para concluir sua verificação na Yoop, utilize o código: {$code}. \n";
        $message .= 'Obrigado por escolher a Yoop!';

        $this->smsGateway->send($phone, $message);

        return true;
    }
}
