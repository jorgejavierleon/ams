<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Self-service permissions granted to the `employee` role. Application code
     * gates on these permissions (not the role name) per Spatie best practice.
     *
     * @var array<int, string>
     */
    private const EMPLOYEE_PERMISSIONS = [
        'RequestOwn:Leave',
        'ViewOwn:Leave',
        'CancelOwn:Leave',
        'ClockOwn:Mark',
        'ViewOwn:Mark',
        'ViewOwn:Workday',
        'ReviewOwn:MarkModification',
        'ViewOwn:Document',
        'SignOwn:Document',
    ];

    /**
     * Self-service permissions granted to the `admin` role directly. Admins get
     * their policy abilities through the super-admin gate, but the attendance
     * widget's store route is guarded by Spatie's `permission:` middleware,
     * which the gate does not bypass — so an admin who clocks in/out must hold
     * these permissions explicitly.
     *
     * @var array<int, string>
     */
    private const ADMIN_PERMISSIONS = [
        'ClockOwn:Mark',
        'ViewOwn:Mark',
    ];

    /**
     * Team leave-management permissions granted to the `supervisor` role by
     * default. Admins can revoke these in the Roles screen to keep leave
     * approval centralized; team scoping itself is enforced in the LeavePolicy.
     *
     * @var array<int, string>
     */
    private const SUPERVISOR_PERMISSIONS = [
        'ViewTeam:Leave',
        'ApproveTeam:Leave',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [];

        foreach (['admin', 'employee', 'supervisor', 'dt', 'saas'] as $role) {
            $roles[$role] = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        foreach ([...self::EMPLOYEE_PERMISSIONS, ...self::SUPERVISOR_PERMISSIONS, ...self::ADMIN_PERMISSIONS] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roles['employee']->givePermissionTo(self::EMPLOYEE_PERMISSIONS);
        $roles['supervisor']->givePermissionTo(self::SUPERVISOR_PERMISSIONS);
        $roles['admin']->givePermissionTo(self::ADMIN_PERMISSIONS);
    }
}
