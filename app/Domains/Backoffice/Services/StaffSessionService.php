<?php

namespace App\Domains\Backoffice\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffSessionService
{
    /** @param  array{email: string, password: string}  $credentials */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        return Auth::attempt($credentials, $remember);
    }

    public function logout(Request $request): void
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
