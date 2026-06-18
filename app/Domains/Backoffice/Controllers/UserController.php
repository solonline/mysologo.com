<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Actions\CreateStaffUserAction;
use App\Domains\Backoffice\Actions\DeleteStaffUserAction;
use App\Domains\Backoffice\Actions\UpdateStaffUserAction;
use App\Domains\Backoffice\DTOs\StaffUserData;
use App\Domains\Backoffice\Queries\ListUsersQuery;
use App\Domains\Backoffice\Requests\StoreStaffUserRequest;
use App\Domains\Backoffice\Requests\UpdateStaffUserRequest;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        protected ListUsersQuery $listUsers,
        protected CreateStaffUserAction $createUser,
        protected UpdateStaffUserAction $updateUser,
        protected DeleteStaffUserAction $deleteUser,
    ) {}

    public function index(Request $request)
    {
        $result = $this->listUsers->execute($request->only(['search', 'role']));

        return view('dashboard.users.index', [
            'users' => $result->users->withQueryString(),
            'roles' => $result->roles,
        ]);
    }

    public function create()
    {
        return view('dashboard.users.create', [
            'roles' => $this->listUsers->allRoles(),
        ]);
    }

    public function store(StoreStaffUserRequest $request)
    {
        $this->createUser->execute(StaffUserData::fromValidated($request->validated()));

        return redirect()->route('dashboard.users')
            ->with('success', 'Utilizador criado com sucesso.');
    }

    public function show(User $user)
    {
        return view('dashboard.users.show', [
            'user' => $this->listUsers->findWithRoles($user),
        ]);
    }

    public function edit(User $user)
    {
        return view('dashboard.users.edit', [
            'user' => $this->listUsers->findWithRoles($user),
            'roles' => $this->listUsers->allRoles(),
        ]);
    }

    public function update(UpdateStaffUserRequest $request, User $user)
    {
        $this->updateUser->execute($user, StaffUserData::fromValidated($request->validated()));

        return redirect()->route('dashboard.users')
            ->with('success', 'Utilizador atualizado com sucesso.');
    }

    public function destroy(User $user)
    {
        $this->deleteUser->execute($user);

        return redirect()->route('dashboard.users')
            ->with('success', 'Utilizador removido com sucesso.');
    }
}
