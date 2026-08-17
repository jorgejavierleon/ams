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
        'RequestOwn:OvertimeAuthorization',
        'ViewOwn:OvertimeAuthorization',
    ];

    /**
     * Self-service permissions granted to the `admin` role directly. Admins get
     * their policy abilities through the super-admin gate, but routes guarded by
     * Spatie's `permission:` middleware — the attendance widget's store route,
     * and `overtime.index` (KOL-43) — are not covered by that gate, so an admin
     * must hold these permissions explicitly to reach them.
     *
     * @var array<int, string>
     */
    private const ADMIN_PERMISSIONS = [
        'ClockOwn:Mark',
        'ViewOwn:Mark',
        'Manage:OvertimeAuthorization',
    ];

    /**
     * Team leave-management permissions granted to the `supervisor` role by
     * default. Admins can revoke these in the Roles screen to keep leave
     * approval centralized; team scoping itself is enforced in the LeavePolicy.
     *
     * `OvertimeAuthorization` follows the same shape: `ViewTeam`/`ApproveTeam`
     * grant the queue and the decision, and `OvertimeAuthorizationPolicy`
     * (KOL-43) enforces that a supervisor only decides their own reports'
     * records, exactly as `LeavePolicy` does for leaves.
     *
     * `Workday` (KOL-71) follows it too: `ViewTeam`/`ApproveTeam` reach
     * Jornadas and act on marks for one's own reports, scoped by
     * `WorkdayPolicy`. This is a separate permission domain from
     * `OvertimeAuthorization` above — deciding a day's overtime from Jornadas
     * still requires `ApproveTeam:OvertimeAuthorization` too, stacked
     * independently rather than folded into the Workday permission.
     *
     * @var array<int, string>
     */
    private const SUPERVISOR_PERMISSIONS = [
        'ViewTeam:Leave',
        'ApproveTeam:Leave',
        'ViewTeam:OvertimeAuthorization',
        'ApproveTeam:OvertimeAuthorization',
        'ViewTeam:Workday',
        'ApproveTeam:Workday',
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
