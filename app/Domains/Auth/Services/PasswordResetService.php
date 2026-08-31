<?php

namespace App\Domains\Auth\Services;

use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function createToken(string $email): ?string
    {
        $customer = Customer::where('email', $email)->first();
        if (!$customer) {
            return null;
        }
        $token = Str::random(60);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($token),
                'created_at' => Carbon::now(),
            ]
        );

        return $token;
    }

    public function validateToken(string $email, string $token): bool
    {
        $record = DB::table('password_reset_tokens')->where('email', $email)->first();
        if (!$record) {
            return false;
        }

        return Hash::check($token, $record->token);
    }

    public function resetPassword(string $email, string $token, string $password): bool
    {
        if (!$this->validateToken($email, $token)) {
            return false;
        }
        $customer = Customer::where('email', $email)->first();
        if (!$customer) {
            return false;
        }
        $customer->email_verified_at = is_null($customer->email_verified_at) ? Carbon::now() : $customer->email_verified_at;
        $customer->password = Hash::make($password);

        $customer->save();
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return true;
    }

    public function resetUrl(string $email, string $token): string
    {
        return route('password.reset', [
            'email' => $email,
            'token' => $token,
        ]);
    }
}
