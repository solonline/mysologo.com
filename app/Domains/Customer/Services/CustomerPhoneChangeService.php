<?php

namespace App\Domains\Customer\Services;

use App\Domains\Onboarding\Services\PhoneVerificationService;
use App\Domains\Sms\Support\SmsLogger;
use App\Exceptions\ApiException;
use App\Exceptions\ErrorCode;
use App\Models\Customer;
use App\Models\PhoneVerification;

class CustomerPhoneChangeService
{
    public function __construct(
        protected PhoneVerificationService $phoneVerification,
    ) {}

    public function isCurrentPhoneVerified(Customer $customer): bool
    {
        $phone = $this->normalizePhone((string) $customer->phone);

        if ($phone === '') {
            return false;
        }

        return $this->phoneVerification->isVerified($customer, $phone);
    }

    public function pendingChangePhone(Customer $customer): ?string
    {
        $currentPhone = $this->normalizePhone((string) $customer->phone);

        $pending = PhoneVerification::query()
            ->where('customer_id', $customer->id)
            ->whereNull('verified_at')
            ->when($currentPhone !== '', fn ($query) => $query->where('phone', '!=', $currentPhone))
            ->latest()
            ->first();

        return $pending?->phone;
    }

    /**
     * @return array{status: string, phone: string, phone_verified: bool, message: string}
     */
    public function requestChange(Customer $customer, string $newPhone): array
    {
        $newPhone = $this->normalizePhone($newPhone);
        $currentPhone = $this->normalizePhone((string) $customer->phone);

        if ($newPhone === '') {
            throw new ApiException(ErrorCode::INTERNAL_ERROR, 422, 'Phone number is required.');
        }

        $pendingPhone = $this->pendingChangePhone($customer);

        if ($newPhone === $currentPhone && ($pendingPhone === null || $newPhone !== $pendingPhone)) {
            throw new ApiException(ErrorCode::PHONE_SAME_AS_CURRENT, 422, 'The new phone number is the same as the current one.');
        }

        if ($pendingPhone !== null && $newPhone === $pendingPhone) {
            return $this->resendPendingChange($customer);
        }

        if ($pendingPhone !== null && $pendingPhone !== $newPhone) {
            $this->clearPendingVerifications($customer);
        }

        SmsLogger::info('phone_change_sms_requested', [
            'customer_id' => $customer->id,
            'current_phone' => $currentPhone,
            'new_phone' => $newPhone,
            'replaced_pending_phone' => $pendingPhone !== null && $pendingPhone !== $newPhone ? $pendingPhone : null,
        ]);

        $sent = $this->phoneVerification->sendSms($customer, $newPhone);

        if (! $sent) {
            SmsLogger::warning('phone_change_sms_not_sent', [
                'customer_id' => $customer->id,
                'current_phone' => $currentPhone,
                'new_phone' => $newPhone,
            ]);

            throw new ApiException(
                ErrorCode::INTERNAL_ERROR,
                502,
                'Unable to send SMS verification code. Check SMS provider configuration and logs.',
            );
        }

        return [
            'status' => 'pending_verification',
            'phone' => $newPhone,
            'phone_verified' => false,
            'message' => 'Verification code sent to the new phone number.',
        ];
    }

    public function confirmChange(Customer $customer, string $newPhone, string $code): Customer
    {
        $newPhone = $this->normalizePhone($newPhone);

        if (! $this->phoneVerification->verify($customer, $newPhone, $code)) {
            SmsLogger::warning('phone_change_confirm_failed', [
                'customer_id' => $customer->id,
                'phone' => $newPhone,
            ]);

            throw new ApiException(ErrorCode::PHONE_VERIFICATION_INVALID, 422, 'Invalid or expired verification code.');
        }

        $customer->update(['phone' => $newPhone]);

        SmsLogger::info('phone_change_confirmed', [
            'customer_id' => $customer->id,
            'phone' => $newPhone,
        ]);

        return $customer->fresh();
    }

    public function confirmPendingChange(Customer $customer, string $code): Customer
    {
        $pendingPhone = $this->pendingChangePhone($customer);

        if ($pendingPhone === null) {
            throw new ApiException(ErrorCode::PHONE_CHANGE_NOT_PENDING, 422, 'No pending phone change to confirm.');
        }

        return $this->confirmChange($customer, $pendingPhone, $code);
    }

    /**
     * @return array{status: string, phone: string, phone_verified: bool, message: string}
     */
    public function resendPendingChange(Customer $customer): array
    {
        $pendingPhone = $this->pendingChangePhone($customer);

        if ($pendingPhone === null) {
            throw new ApiException(ErrorCode::PHONE_CHANGE_NOT_PENDING, 422, 'No pending phone change to resend.');
        }

        SmsLogger::info('phone_change_sms_resend', [
            'customer_id' => $customer->id,
            'phone' => $pendingPhone,
        ]);

        $sent = $this->phoneVerification->sendSms($customer, $pendingPhone);

        if (! $sent) {
            SmsLogger::warning('phone_change_sms_resend_failed', [
                'customer_id' => $customer->id,
                'phone' => $pendingPhone,
            ]);

            throw new ApiException(
                ErrorCode::INTERNAL_ERROR,
                502,
                'Unable to send SMS verification code. Check SMS provider configuration and logs.',
            );
        }

        return [
            'status' => 'pending_verification',
            'phone' => $pendingPhone,
            'phone_verified' => false,
            'message' => 'Verification code resent to the pending phone number.',
        ];
    }

    public function pendingCurrentPhoneVerification(Customer $customer): ?PhoneVerification
    {
        $phone = $this->normalizePhone((string) $customer->phone);

        if ($phone === '') {
            return null;
        }

        return PhoneVerification::query()
            ->where('customer_id', $customer->id)
            ->where('phone', $phone)
            ->whereNull('verified_at')
            ->latest()
            ->first();
    }

    /**
     * @return array{status: string, phone: string, phone_verified: bool, message: string}
     */
    public function sendCurrentPhoneVerification(Customer $customer): array
    {
        $phone = $this->normalizePhone((string) $customer->phone);

        if ($phone === '') {
            throw new ApiException(ErrorCode::INTERNAL_ERROR, 422, 'Phone number is required.');
        }

        if ($this->isCurrentPhoneVerified($customer)) {
            throw new ApiException(ErrorCode::PHONE_SAME_AS_CURRENT, 422, 'The current phone number is already verified.');
        }

        SmsLogger::info('current_phone_sms_requested', [
            'customer_id' => $customer->id,
            'phone' => $phone,
        ]);

        $sent = $this->phoneVerification->sendSms($customer, $phone);

        if (! $sent) {
            SmsLogger::warning('current_phone_sms_not_sent', [
                'customer_id' => $customer->id,
                'phone' => $phone,
            ]);

            throw new ApiException(
                ErrorCode::INTERNAL_ERROR,
                502,
                'Unable to send SMS verification code. Check SMS provider configuration and logs.',
            );
        }

        return [
            'status' => 'pending_verification',
            'phone' => $phone,
            'phone_verified' => false,
            'message' => 'Verification code sent to the current phone number.',
        ];
    }

    public function confirmCurrentPhone(Customer $customer, string $code): Customer
    {
        $phone = $this->normalizePhone((string) $customer->phone);

        if ($phone === '') {
            throw new ApiException(ErrorCode::INTERNAL_ERROR, 422, 'Phone number is required.');
        }

        if (! $this->phoneVerification->verify($customer, $phone, $code)) {
            SmsLogger::warning('current_phone_confirm_failed', [
                'customer_id' => $customer->id,
                'phone' => $phone,
            ]);

            throw new ApiException(ErrorCode::PHONE_VERIFICATION_INVALID, 422, 'Invalid or expired verification code.');
        }

        SmsLogger::info('current_phone_confirmed', [
            'customer_id' => $customer->id,
            'phone' => $phone,
        ]);

        return $customer->fresh();
    }

    public function cancelPendingChange(Customer $customer): void
    {
        $pendingPhone = $this->pendingChangePhone($customer);

        if ($pendingPhone === null) {
            throw new ApiException(ErrorCode::PHONE_CHANGE_NOT_PENDING, 422, 'No pending phone change to cancel.');
        }

        $this->clearPendingVerifications($customer);

        SmsLogger::info('phone_change_cancelled', [
            'customer_id' => $customer->id,
            'phone' => $pendingPhone,
        ]);
    }

    private function clearPendingVerifications(Customer $customer): void
    {
        $currentPhone = $this->normalizePhone((string) $customer->phone);

        PhoneVerification::query()
            ->where('customer_id', $customer->id)
            ->whereNull('verified_at')
            ->when($currentPhone !== '', fn ($query) => $query->where('phone', '!=', $currentPhone))
            ->delete();
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', trim($phone)) ?? '';
    }
}
