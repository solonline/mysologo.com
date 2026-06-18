<?php

namespace App\Domains\Onboarding\Repositories;

use App\Domains\Onboarding\Contracts\PhoneVerificationRepositoryInterface;
use App\Models\Customer;
use App\Models\PhoneVerification;
use Illuminate\Support\Carbon;

class PhoneVerificationRepository implements PhoneVerificationRepositoryInterface
{
    public function createOrUpdate(Customer $customer, string $phone, string $code): PhoneVerification
    {
        return PhoneVerification::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'phone' => $phone,
            ],
            [
                'code' => $code,
                'verified_at' => null,
            ],
        );
    }

    public function verify(Customer $customer, string $phone, string $code): bool
    {
        $verification = PhoneVerification::where('customer_id', $customer->id)
            ->where('phone', $phone)
            ->whereNull('verified_at')
            ->first();

        if ($verification) {
            $expiresAt = $verification->created_at->copy()->addMinutes(2);
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
