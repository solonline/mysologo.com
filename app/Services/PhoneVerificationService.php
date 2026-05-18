<?php

namespace App\Services;

use App\Models\PhoneVerification;
use App\Models\Customer;
use Illuminate\Support\Carbon;

class PhoneVerificationService
{
    public function __construct(
        protected \App\Contracts\PhoneVerificationRepositoryInterface $repository
    ) {}

    public function createOrUpdate(Customer $customer, string $phone, string $code): PhoneVerification
    {
        return $this->repository->createOrUpdate($customer, $phone, $code);
    }

    public function verify(Customer $customer, string $phone, string $code): bool
    {
        return $this->repository->verify($customer, $phone, $code);
    }

    public function isVerified(Customer $customer, string $phone): bool
    {
        return $this->repository->isVerified($customer, $phone);
    }

    /**
     * Gera código, salva e envia SMS de verificação
     * @return bool true se SMS enviado, false se já estava verificado
     */
    public function sendSms(Customer $customer, string $phone): bool
    {
        // Não envia se já estiver verificado
        if ($this->isVerified($customer, $phone)) {
            return false;
        }

        // Gera código numérico de 6 dígitos
        $code = random_int(100000, 999999);
        $this->createOrUpdate($customer, $phone, (string)$code);

        // Monta mensagem profissional
        $message = "Olá! Para concluir sua verificação na Yoop, utilize o código: $code. \n";
        $message .= "Obrigado por escolher a Yoop!";

        // Envia SMS
        app(\App\Providers\SmsProvider::class)->sendSms([$phone], $message);
        return true;
    }
}
