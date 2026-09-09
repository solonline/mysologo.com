<?php

namespace App\Domains\Onboarding\Services;

use App\Domains\Onboarding\Contracts\PhoneVerificationRepositoryInterface;
use App\Domains\Onboarding\Enums\CustomerLifecycleStatus;
use App\Domains\Sms\Contracts\SmsGatewayInterface;
use App\Domains\Sms\Support\SmsLogger;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
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
     * Envia OTP no onboarding. Sem `phone` reenvia para o número da conta.
     * Com um número diferente, corrige o telefone ainda não verificado e envia para o novo.
     *
     * @return bool true se SMS enviado, false se o telefone da conta já estava verificado
     */
    public function sendForOnboarding(Customer $customer, ?string $requestedPhone = null): bool
    {
        $requestedPhone = $this->normalizePhone((string) $requestedPhone);
        $currentPhone = $this->normalizePhone((string) $customer->phone);

        if ($requestedPhone !== '' && $requestedPhone !== $currentPhone) {
            if ($currentPhone !== '' && $this->isVerified($customer, $currentPhone)) {
                throw new ApiException(
                    ErrorCode::PHONE_ALREADY_VERIFIED,
                    422,
                    'O telefone actual já está verificado. Use POST /api/customer/phone para o alterar.',
                );
            }

            $customer->update(['phone' => $requestedPhone]);
            $currentPhone = $requestedPhone;

            SmsLogger::info('onboarding_phone_corrected', [
                'customer_id' => $customer->id,
                'phone' => $currentPhone,
            ]);
        }

        if ($currentPhone === '') {
            throw new ApiException(ErrorCode::INTERNAL_ERROR, 422, 'Phone number is required.');
        }

        return $this->sendSms($customer, $currentPhone);
    }

    /**
     * Gera código, persiste e envia SMS de verificação.
     *
     * @return bool true se SMS enviado, false se já estava verificado
     */
    public function sendSms(Customer $customer, string $phone): bool
    {
        if ($this->isVerified($customer, $phone)) {
            SmsLogger::info('phone_verification_sms_skipped_already_verified', [
                'customer_id' => $customer->id,
                'phone' => $phone,
            ]);

            return false;
        }

        $code = random_int(100000, 999999);
        $verification = $this->createOrUpdate($customer, $phone, (string) $code);

        $message = "Ola utilize o código: {$code}. \n";
        $message .= 'Obrigado por escolher a Yoop.';

        // $message = $code;

        SmsLogger::info('phone_verification_sms_dispatch', [
            'customer_id' => $customer->id,
            'phone' => $phone,
            'code' => (string) $code,
        ]);

        $result = $this->smsGateway->send($phone, $message);

        SmsLogger::info('phone_verification_sms_result', [
            'customer_id' => $customer->id,
            'phone' => $phone,
            'provider_status' => $result['status'] ?? 'unknown',
            'http_code' => $result['http_code'] ?? null,
            'provider_body' => $result['body'] ?? null,
        ]);

        if (($result['status'] ?? 'failed') !== 'success') {
            $verification->delete();

            return false;
        }

        return true;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', trim($phone)) ?? '';
    }
}
