<?php

namespace App\Domains\Backoffice\Controllers;

use App\Domains\Backoffice\Actions\CreateRoleAction;
use App\Domains\Backoffice\Actions\DeleteRoleAction;
use App\Domains\Backoffice\Actions\UpdateRoleAction;
use App\Domains\Backoffice\DTOs\RoleData;
use App\Domains\Backoffice\Queries\ListRolesQuery;
use App\Domains\Backoffice\Requests\StoreRoleRequest;
use App\Domains\Backoffice\Requests\UpdateRoleRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        protected ListRolesQuery $listRoles,
        protected CreateRoleAction $createRole,
        protected UpdateRoleAction $updateRole,
        protected DeleteRoleAction $deleteRole,
    ) {}

    public function index(Request $request)
    {
        return view('dashboard.roles.index', [
            'roles' => $this->listRoles->execute($request->only(['search']))->withQueryString(),
        ]);
    }

    public function create()
    {
        $form = $this->listRoles->formData();

        return view('dashboard.roles.create', [
            'permissions' => $form->permissions,
        ]);
    }

    public function store(StoreRoleRequest $request)
    {
        $this->createRole->execute(RoleData::fromValidated($request->validated()));

        return redirect()->route('dashboard.roles')
            ->with('success', 'Role criada com sucesso.');
    }

    public function show(Role $role)
    {
        $detail = $this->listRoles->detail($role);

        return view('dashboard.roles.show', [
            'role' => $detail->role,
            'users' => $detail->users,
        ]);
    }

    public function edit(Role $role)
    {
        $form = $this->listRoles->formData($role);

        return view('dashboard.roles.edit', [
            'role' => $role,
            'permissions' => $form->permissions,
            'rolePermissions' => $form->selectedPermissions,
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $this->updateRole->execute($role, RoleData::fromValidated($request->validated()));

        return redirect()->route('dashboard.roles')
            ->with('success', 'Role atualizada com sucesso.');
    }

    public function destroy(Role $role)
    {
        $this->deleteRole->execute($role);

        return redirect()->route('dashboard.roles')
            ->with('success', 'Role removida com sucesso.');
    }
}
