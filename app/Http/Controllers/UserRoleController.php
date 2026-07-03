<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserRoleController extends Controller
{
    public function show(User $user): Response
    {
        $allRoles = Role::orderBy('name')->get();
        $assignedIds = $user->roles->pluck('id')->all();

        return Inertia::render('users/roles', [
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'roles' => $allRoles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'assigned' => in_array($role->id, $assignedIds),
            ]),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $user->syncRoles($validated['roles']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Roles updated.')]);

        return to_route('users.roles', $user);
    }
}
