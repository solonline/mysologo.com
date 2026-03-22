<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AuthService
{
    public function register(array $data)
    {
        $password = Hash::make($data['password']);
        $user = User::create($data + ['password' => $password]);

        $user->assignRole(Role::findByName($data['role'], 'api'));
        // Gera token com Sanctum
        return $this->login($data);
    }

    public function createDiditSession()
    {
        $diditService = new DiditService();
        $sessionData = [
            'workflow_id'   => config('services.didit.workflow_id'),
            'user_id'       => auth()->id(),
            'callback'      => 'https://yourapp.com/api/didit/callback', //TODO definir callback
            'language'      => auth()->user()->language ?? 'en',
        ];
        return $diditService->createSession($sessionData);
    }

    public function login(array $data)
    {
        $user = User::where('email', $data['email'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return null;
        }
        $token = $user->createToken('api_token', [], now()->addMinute())->plainTextToken;

        return [
            'user' => $user->find($user->id),
            'token' => $token,
        ];
    }
}
