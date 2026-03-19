<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function register(array $data)
    {
        $password = Hash::make($data['password']);
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'password' => $password,
        ]);
        // Gera token com Sanctum
        return $this->login($data);
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
