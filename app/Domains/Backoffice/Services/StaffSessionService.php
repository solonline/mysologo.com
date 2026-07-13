<?php

namespace App\Domains\Backoffice\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffSessionService
{
    protected function guard()
    {
        return Auth::guard('web');
    }

    /** @param  array{email: string, password: string}  $credentials */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        return $this->guard()->attempt($credentials, $remember);
    }

    public function logout(Request $request): void
    {
        $this->guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
