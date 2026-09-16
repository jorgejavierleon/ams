<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTablePerPage;
use App\Concerns\ResolvesTableSort;
use App\Support\CurrentOrganization;
use App\Support\RolePresenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use ResolvesTablePerPage;
    use ResolvesTableSort;

    /**
     * Number of user avatars shown per role in the index list before the
     * remainder collapses into a "+N" overflow bubble.
     */
    private const AVATAR_LIMIT = 5;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value() ?: null;
        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['name', 'permissions_count', 'users_count'],
            'name',
        );
        $perPage = $this->resolveTablePerPage($request);

        // Spatie roles are shared globally across tenants, so the users
        // relation must be scoped to the current organization explicitly —
        // it is not covered by an org-scoped global scope like Position is.
        $scopeToCurrentOrganization = fn ($query) => $query->where('organization_id', CurrentOrganization::id());

        $roles = Role::withCount([
            'permissions',
            'users' => $scopeToCurrentOrganization,
        ])
            ->with(['users' => fn ($query) => $scopeToCurrentOrganization($query)
                ->select('users.id', 'users.name')
                ->with('media')
                ->orderBy('name')
                ->limit(self::AVATAR_LIMIT)])
            ->tap([RolePresenter::class, 'excludeProtected'])
            ->when($search, fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('roles/index', [
            'roles' => $roles->through(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => RolePresenter::roleLabel($role->name),
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
                // Role::users() is typed by Spatie as Collection<int, Model>
                // (the related model is resolved dynamically per guard), so
                // avatar fields are read through the generic Model accessor
                // rather than a User-typed closure parameter.
                'avatars' => $role->users->map(fn (Model $user): array => [
                    'id' => (int) $user->getAttribute('id'),
                    'name' => (string) $user->getAttribute('name'),
                    'avatar' => $user->getAttribute('avatar'),
                ])->all(),
            ]),
            'filters' => ['search' => $search, 'sort' => $sort, 'direction' => $direction],
        ]);
    }

    public function show(Role $role): Response
    {
        abort_if(in_array($role->name, RolePresenter::PROTECTED_ROLES), 403);

        $allPermissions = Permission::orderBy('name')->get();
        $assignedIds = $role->permissions->pluck('id')->all();

        $grouped = $allPermissions
            ->groupBy(fn (Permission $permission) => RolePresenter::groupKey($permission->name))
            ->map(fn ($permissions, $groupKey) => [
                'group' => RolePresenter::groupLabel($groupKey),
                'permissions' => $permissions->map(fn (Permission $permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'label' => RolePresenter::permissionLabel($permission->name),
                    'assigned' => in_array($permission->id, $assignedIds),
                ])->values(),
            ])
            ->values();

        return Inertia::render('roles/show', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => RolePresenter::roleLabel($role->name),
            ],
            'permissionGroups' => $grouped,
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if(in_array($role->name, RolePresenter::PROTECTED_ROLES), 403);

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
}
