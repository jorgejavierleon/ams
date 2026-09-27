<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTablePerPage;
use App\Concerns\ResolvesTableSort;
use App\Support\CurrentOrganization;
use App\Support\RolePresenter;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
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
            // Unscoped, for the delete-confirmation warning: roles are shared
            // globally, so deleting one detaches every holder in every
            // organization, not just the ones this admin can see in `users`.
            'users as all_users_count',
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
                // Unlike the plain 'permissions'/'users' withCount() entries
                // above, this is an aliased count ('users as all_users_count'),
                // which Larastan's withCount() property inference doesn't
                // recognize — read through the generic accessor instead.
                'all_users_count' => $role->getAttribute('all_users_count'),
                'is_system_role' => in_array($role->name, RolePresenter::SYSTEM_ROLES),
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

        return Inertia::render('roles/show', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => RolePresenter::roleLabel($role->name),
                'is_system_role' => in_array($role->name, RolePresenter::SYSTEM_ROLES),
            ],
            'permissionGroups' => $this->permissionGroups($role),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('roles/create', [
            'permissionGroups' => $this->permissionGroups(null),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);

        // Resolve to Permission models by id: the form submits ids as strings,
        // and syncPermissions() would otherwise treat a string id as a name.
        $permissions = Permission::whereKey($validated['permissions'])->get();

        $role->syncPermissions($permissions);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.roles.flash.created')]);

        return to_route('roles.show', $role);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if(in_array($role->name, RolePresenter::PROTECTED_ROLES), 403);

        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role),
                function (string $attribute, mixed $value, \Closure $fail) use ($role): void {
                    if (! RolePresenter::isRenameable($role->name) && $value !== $role->name) {
                        $fail(__('ui.roles.system_role_rename_blocked'));
                    }
                },
            ],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->update(['name' => $validated['name']]);

        // Resolve to Permission models by id: the form submits ids as strings,
        // and syncPermissions() would otherwise treat a string id as a name.
        $permissions = Permission::whereKey($validated['permissions'])->get();

        $role->syncPermissions($permissions);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.roles.flash.updated')]);

        return to_route('roles.show', $role);
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_unless(RolePresenter::isDeletable($role->name), 403);

        // No explicit detach() needed: model_has_roles.role_id has
        // cascadeOnDelete(), so the DB drops every holder's pivot row
        // (in every organization, since roles are shared globally — see
        // index()) the moment this delete() runs.
        $role->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.roles.flash.deleted')]);

        return to_route('roles.index');
    }

    /**
     * Clone any visible role (system or custom) into a new, ordinary custom
     * role that carries the source role's current permissions. The clone
     * always lands outside SYSTEM_ROLES/PROTECTED_ROLES — its generated name
     * always carries a "(copy)" suffix — so it stays freely renameable and
     * deletable even when cloned from admin/employee/supervisor.
     */
    public function clone(Role $role): RedirectResponse
    {
        abort_if(in_array($role->name, RolePresenter::PROTECTED_ROLES), 403);

        $clone = Role::create([
            'name' => $this->uniqueCloneName($role->name),
            'guard_name' => 'web',
        ]);

        $clone->syncPermissions($role->permissions);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.roles.flash.cloned')]);

        return to_route('roles.show', $clone);
    }

    /**
     * Build a "<name> (copy)" name for a clone, deduplicated as
     * "<name> (copy 2)", "<name> (copy 3)", … against existing role names.
     * The source name is truncated as needed so the result never exceeds the
     * roles.name column's 255-character limit (the same limit store()/
     * update() enforce via 'max:255').
     */
    private function uniqueCloneName(string $originalName): string
    {
        $candidate = $this->truncatedCloneName($originalName, ' (copy)');

        if (! Role::where('name', $candidate)->where('guard_name', 'web')->exists()) {
            return $candidate;
        }

        $suffix = 2;
        do {
            $candidate = $this->truncatedCloneName($originalName, " (copy {$suffix})");
            $suffix++;
        } while (Role::where('name', $candidate)->where('guard_name', 'web')->exists());

        return $candidate;
    }

    private function truncatedCloneName(string $originalName, string $suffix): string
    {
        $maxNameLength = 255 - mb_strlen($suffix);

        return mb_substr($originalName, 0, $maxNameLength).$suffix;
    }

    /**
     * Resync a system role's (admin/employee/supervisor) permissions to the
     * deploy-time default defined in {@see RoleSeeder::defaultPermissionsFor()},
     * discarding whatever an organization has since edited it to.
     */
    public function restoreDefaults(Role $role): RedirectResponse
    {
        abort_unless(in_array($role->name, RolePresenter::SYSTEM_ROLES), 403);

        $permissionNames = RoleSeeder::defaultPermissionsFor($role->name);
        $permissions = Permission::whereIn('name', $permissionNames)->where('guard_name', 'web')->get();

        $role->syncPermissions($permissions);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.roles.flash.restored')]);

        return to_route('roles.show', $role);
    }

    /**
     * Build the permission checklist grouped for the role detail/create
     * screens, marking permissions already on `$role` (or none, for a new
     * role) as assigned.
     *
     * @return Collection<int, array{group: string, permissions: Collection<int, array{id: int, name: string, label: string, assigned: bool}>}>
     */
    private function permissionGroups(?Role $role): Collection
    {
        $allPermissions = Permission::orderBy('name')->get();
        $assignedIds = $role?->permissions->pluck('id')->all() ?? [];

        return $allPermissions
            ->groupBy(fn (Permission $permission) => RolePresenter::groupKey($permission->name))
            ->map(fn (Collection $permissions, $groupKey) => [
                'group' => RolePresenter::groupLabel($groupKey),
                'permissions' => $permissions->map(fn (Permission $permission) => [
                    'id' => (int) $permission->id,
                    'name' => $permission->name,
                    'label' => RolePresenter::permissionLabel($permission->name),
                    'assigned' => in_array($permission->id, $assignedIds),
                ])->values(),
            ])
            ->values();
    }
}
