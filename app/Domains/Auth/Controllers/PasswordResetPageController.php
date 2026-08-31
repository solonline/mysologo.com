<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Services\PasswordResetService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordPageRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordResetPageController extends Controller
{
    public function __construct(
        private PasswordResetService $passwordResetService,
    ) {}

    public function show(Request $request): View
    {
        $email = (string) $request->query('email', '');
        $token = (string) $request->query('token', '');

        if ($email === '' || $token === '' || ! $this->passwordResetService->validateToken($email, $token)) {
            return view('auth.password-reset', [
                'status' => 'invalid',
                'email' => $email,
                'token' => '',
            ]);
        }

        return view('auth.password-reset', [
            'status' => 'form',
            'email' => $email,
            'token' => $token,
        ]);
    }

    public function update(ResetPasswordPageRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $ok = $this->passwordResetService->resetPassword(
            $data['email'],
            $data['token'],
            $data['password'],
        );

        if (! $ok) {
            return back()
                ->withErrors(['token' => 'Este link de redefinição não é válido ou já foi utilizado.'])
                ->withInput($request->only('email'));
        }

        return redirect()->route('password.reset.success')->with('password_reset_done', true);
    }

    public function success(): View
    {
        if (! session('password_reset_done')) {
            return view('auth.password-reset', [
                'status' => 'invalid',
                'email' => '',
                'token' => '',
            ]);
        }

        return view('auth.password-reset', [
            'status' => 'success',
            'email' => '',
            'token' => '',
        ]);
    }
}
