<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /**
     * Listado de roles con sus permisos agrupados.
     */
    public function index(Request $request): Response
    {
        $this->authorizeManage($request);

        $roles = Role::query()
            ->withCount('users')
            ->with('permissions:id,slug,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'users_count' => (int) ($role->users_count ?? 0),
                'permissions' => $role->permissions->pluck('slug')->values()->all(),
            ])
            ->values()
            ->all();

        $permissionGroups = Permission::query()
            ->orderBy('group')
            ->orderBy('name')
            ->get(['slug', 'name', 'group'])
            ->groupBy('group')
            ->map(fn ($permissions, string $group): array => [
                'group' => $group,
                'permissions' => $permissions
                    ->map(fn (Permission $permission): array => [
                        'slug' => $permission->slug,
                        'name' => $permission->name,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('roles/Index', [
            'roles' => $roles,
            'permission_groups' => $permissionGroups,
            'can' => [
                'manage' => true,
            ],
        ]);
    }

    /**
     * Reemplaza los permisos de un rol editable.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $this->authorizeManage($request);

        if ($role->is_system) {
            return back()->with(
                'error',
                "El rol «{$role->name}» es del sistema y no se puede modificar.",
            );
        }

        $permissionIds = Permission::query()
            ->whereIn('slug', $request->validated('permissions'))
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);

        return back()->with('success', "Permisos del rol «{$role->name}» actualizados correctamente.");
    }

    private function authorizeManage(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->hasPermission('roles.manage'),
            403,
        );
    }
}
