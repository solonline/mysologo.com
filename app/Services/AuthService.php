<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AuthService
{
    public function register(array $data)
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        $data['register'] = true; // Flag para indicar que é um registro, não login

        try {
            DB::beginTransaction();
            $user = User::create($data);
            \Log::info('User created with ID: ' . $user->id . ' and role: ' . $data['role']);
            $user->assignRole(Role::findByName($data['role'], 'api'));
            \Log::info('veja', ['role: ' => Role::findByName($data['role'], 'api')]);

            // Autentica o usuário para que auth()->id() funcione
            auth()->login($user);

            $this->createDiditSession();

            $sessionDidit = $user->diditSessions()->latest()->first();

            DB::commit();

            $loginData = $this->login($data);

            return [
                'user' => $loginData['user'],
                'token' => $loginData['token'],
                'didit_session' => $sessionDidit,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw new \Exception('Invalid role provided.', 0, $e);
        }
    }

    public function createDiditSession()
    {
        $diditService = new DiditService();
        $sessionData = [
            'workflow_id' => config('services.didit.workflow_id'),
            'user_id' => auth()->id(),
            'callback' => 'https://yourapp.com/api/didit/callback', //TODO definir callback
            'language' => auth()->user()->language ?? 'en',
        ];
        $sessionInfo = $diditService->createSession($sessionData);

        // Salva sessão no banco
        $userDiditSession = new \App\Models\UserDiditSession();
        $userDiditSession->user_id      = auth()->id();
        $userDiditSession->session_id   = $sessionInfo['session_id'];
        $userDiditSession->workflow_id  = $sessionInfo['workflow_id'];
        $userDiditSession->session_url  = $sessionInfo['url'];
        $userDiditSession->status       = $sessionInfo['status'];
        $userDiditSession->save();
    }

    public function updateDiditSessionStatus(string $sessionId, string $status)
    {
        $session = \App\Models\UserDiditSession::where('session_id', $sessionId)->first();
        if ($session) {
            $session->status = $status;
            $session->save();
        }
    }

    public function login(array $data)
    {
        $user = User::where('email', $data['email'])->first();
        // Se for registro, não exige senha (cadastro por etapas)
        if (isset($data['register']) && $data['register'] === true) {
            if (!$user) {
                return null;
            }
        } else {
            if (!$user || !Hash::check($data['password'], $user->password)) {
                return null;
            }
        }
        $token = $user->createToken('api_token', [], now()->addMinute())->plainTextToken;

        return [
            'user' => $user->find($user->id),
            'token' => $token,
        ];
    }
}
