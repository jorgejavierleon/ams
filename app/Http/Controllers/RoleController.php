<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTableSort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use ResolvesTableSort;

    /** Roles reserved for system use — admins cannot manage these. */
    private const PROTECTED_ROLES = ['admin', 'dt', 'saas'];

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['name', 'permissions_count'],
            'name',
        );

        $roles = Role::withCount('permissions')
            ->whereNotIn('name', self::PROTECTED_ROLES)
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('roles/index', [
            'roles' => $roles->through(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions_count' => $role->permissions_count,
            ]),
            'filters' => ['search' => $search, 'sort' => $sort, 'direction' => $direction],
        ]);
    }

    public function show(Role $role): Response
    {
        abort_if(in_array($role->name, self::PROTECTED_ROLES), 403);

        $allPermissions = Permission::orderBy('name')->get();
        $assignedIds = $role->permissions->pluck('id')->all();

        $grouped = $allPermissions
            ->groupBy(fn (Permission $permission) => $this->groupName($permission->name))
            ->map(fn ($permissions, $group) => [
                'group' => $group,
                'permissions' => $permissions->map(fn (Permission $permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'assigned' => in_array($permission->id, $assignedIds),
                ])->values(),
            ])
            ->values();

        return Inertia::render('roles/show', [
            'role' => ['id' => $role->id, 'name' => $role->name],
            'permissionGroups' => $grouped,
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if(in_array($role->name, self::PROTECTED_ROLES), 403);

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        // Resolve to Permission models by id: the form submits ids as strings,
        // and syncPermissions() would otherwise treat a string id as a name.
        $permissions = Permission::whereKey($validated['permissions'])->get();

        $role->syncPermissions($permissions);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Permissions updated.')]);

        return to_route('roles.show', $role);
    }

    private function groupName(string $permissionName): string
    {
        $parts = explode('_', $permissionName);
        $resource = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $parts[0];

        return ucwords($resource);
    }
}
