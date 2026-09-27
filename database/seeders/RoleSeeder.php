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
     * `PayrollReport` (KOL-18) follows the same shape: `View` and `Export` are
     * kept separate because viewing a report and producing the file that
     * leaves the building are different levels of exposure for this
     * company-wide, sensitive payroll data — a future role could hold one
     * without the other, even though today only `admin` (the RRHH/tenant-admin
     * role) holds both.
     *
     * `Import:Employee` (KOL-94.6) gates the whole bulk-import wizard. Admin
     * only by default — supervisors hold no Employee-record permissions
     * today, and bulk-creating/overwriting records is a bigger capability
     * jump than anything they currently have; a tenant admin can grant it to
     * another role later via the Roles screen.
     *
     * `Create:Leave` (KOL-127) gates creating a leave request on behalf of
     * another employee — both the admin web form and its MCP tool
     * equivalent authorize against this same permission via `LeavePolicy`.
     *
     * `View:Employee`/`Manage:Employee` (KOL-95/KOL-133.4) gate the employees
     * CRUD routes, which used to be role:admin-only. Now that the admin role
     * is editable (KOL-133.3), that middleware would let an organization
     * silently change who can reach these routes by editing the role, so
     * they're granted here like every other admin capability instead.
     *
     * `ViewAny:DocumentTemplate`/`Create:DocumentTemplate`/
     * `Update:DocumentTemplate`/`Delete:DocumentTemplate` (KOL-130) gate
     * `DocumentTemplateController` and its MCP tool equivalents via
     * `DocumentTemplatePolicy`, replacing the route-level role:admin-only
     * check those actions relied on before.
     *
     * `Create:Document` gates the generate-document MCP tool via
     * `DocumentPolicy`, the same ability the web document-creation form uses.
     *
     * `Manage:Role`, `Manage:Position`, `Manage:CostCenter`, `Manage:Company`,
     * `Manage:Premise`, `Manage:Shift`, `Manage:Holiday`, and
     * `Manage:Document` each gate one settings screen's routes in
     * `routes/web.php`. Granting them here rather than checking the `admin`
     * role by name means an organization that edits what its admin role can
     * do actually changes who reaches these screens.
     *
     * Public: this is also the built-in admin capability surface the Owner
     * sees on the frontend nav (see HandleInertiaRequests::effectivePermissionNames()),
     * regardless of what an organization has since edited the `admin` role to
     * hold via the Roles screen. That surface deliberately excludes
     * EMPLOYEE_PERMISSIONS/SUPERVISOR_PERMISSIONS — the frontend's
     * `isEmployee` nav check keys off holding `ViewOwn:Leave`, so the Owner
     * must not appear to hold it. The `admin` role itself is still seeded
     * with the union of all three lists below, since it is a real,
     * fully-permissioned role rather than this synthetic Owner surface.
     *
     * @var array<int, string>
     */
    public const ADMIN_PERMISSIONS = [
        'ClockOwn:Mark',
        'ViewOwn:Mark',
        'Manage:OvertimeAuthorization',
        'View:PayrollReport',
        'Export:PayrollReport',
        'Import:Employee',
        'Create:Leave',
        'View:Employee',
        'Manage:Employee',
        'ViewAny:DocumentTemplate',
        'Create:DocumentTemplate',
        'Update:DocumentTemplate',
        'Delete:DocumentTemplate',
        'Create:Document',
        'Manage:Role',
        'Manage:Position',
        'Manage:CostCenter',
        'Manage:Company',
        'Manage:Premise',
        'Manage:Shift',
        'Manage:Holiday',
        'Manage:Document',
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

        $allPermissions = [...self::EMPLOYEE_PERMISSIONS, ...self::SUPERVISOR_PERMISSIONS, ...self::ADMIN_PERMISSIONS];

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roles['employee']->givePermissionTo(self::EMPLOYEE_PERMISSIONS);
        $roles['supervisor']->givePermissionTo(self::SUPERVISOR_PERMISSIONS);
        $roles['admin']->givePermissionTo($allPermissions);
    }
}
