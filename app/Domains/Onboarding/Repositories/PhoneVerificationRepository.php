<?php

namespace App\Domains\Onboarding\Repositories;

use App\Domains\Onboarding\Contracts\PhoneVerificationRepositoryInterface;
use App\Models\Customer;
use App\Models\PhoneVerification;
use Illuminate\Support\Carbon;

class PhoneVerificationRepository implements PhoneVerificationRepositoryInterface
{
    public const CODE_TTL_MINUTES = 10;

    public function createOrUpdate(Customer $customer, string $phone, string $code): PhoneVerification
    {
        $verification = PhoneVerification::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'phone' => $phone,
            ],
            [
                'code' => $code,
                'verified_at' => null,
            ],
        );

        $verification->updated_at = now();
        $verification->save();

        return $verification;
    }

    public function verify(Customer $customer, string $phone, string $code): bool
    {
        $code = trim($code);

        $verification = PhoneVerification::where('customer_id', $customer->id)
            ->where('phone', $phone)
            ->whereNull('verified_at')
            ->first();

        if ($verification) {
            $issuedAt = $verification->updated_at ?? $verification->created_at;
            $expiresAt = $issuedAt->copy()->addMinutes(self::CODE_TTL_MINUTES);
            if (now()->greaterThan($expiresAt)) {
                return false;
            }
            if ($verification->code === $code) {
                $verification->verified_at = Carbon::now();
                $verification->save();

                return true;
            }
        }

        return false;
    }

    public function isVerified(Customer $customer, string $phone): bool
    {
        return PhoneVerification::where('customer_id', $customer->id)
            ->where('phone', $phone)
            ->whereNotNull('verified_at')
            ->exists();
    }
}
