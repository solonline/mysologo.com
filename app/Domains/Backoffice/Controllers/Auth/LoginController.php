<?php

namespace App\Domains\Backoffice\Controllers\Auth;

use App\Domains\Backoffice\Requests\StaffLoginRequest;
use App\Domains\Backoffice\Services\StaffSessionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __construct(
        protected StaffSessionService $session,
    ) {}

    public function showLogin()
    {
        if (Auth::guard('web')->check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(StaffLoginRequest $request)
    {
        if ($this->session->attempt($request->validated(), $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        return back()
            ->withInput($request->only('email', 'remember'))
            ->withErrors(['email' => 'Credenciais inválidas.']);
    }

    public function logout(Request $request)
    {
        $this->session->logout($request);

        return redirect()->route('login');
    }
}
