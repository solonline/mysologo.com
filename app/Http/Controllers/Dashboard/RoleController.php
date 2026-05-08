<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $query = Role::query()
            ->where('guard_name', 'web')
            ->withCount(['permissions', 'users']);

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $roles = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('dashboard.roles.index', compact('roles'));
    }

    public function create()
    {
        $permissions = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($p) => explode('.', $p->name)[0]);

        return view('dashboard.roles.create', compact('permissions'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);

        if (!empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return redirect()->route('dashboard.roles')
            ->with('success', 'Role criada com sucesso.');
    }

    public function show(Role $role)
    {
        $role->load('permissions');
        $users = $role->users()->latest()->paginate(10);

        return view('dashboard.roles.show', compact('role', 'users'));
    }

    public function edit(Role $role)
    {
        $permissions = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->groupBy(fn ($p) => explode('.', $p->name)[0]);

        $rolePermissions = $role->permissions->pluck('name')->toArray();

        return view('dashboard.roles.edit', compact('role', 'permissions', 'rolePermissions'));
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:100', 'unique:roles,name,' . $role->id],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        return redirect()->route('dashboard.roles')
            ->with('success', 'Role atualizada com sucesso.');
    }

    public function destroy(Role $role)
    {
        $role->delete();

        return redirect()->route('dashboard.roles')
            ->with('success', 'Role removida com sucesso.');
    }
}
