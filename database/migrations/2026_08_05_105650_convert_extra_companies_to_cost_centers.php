<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Collapse each organization onto a single company and turn whatever it was
 * using the extra ones for into real cost centres.
 *
 * Before KOL-32 a tenant could hold several companies and the payroll
 * *código contable* lived on `companies.code` (KOL-30). Both are wrong: the
 * company is the employer legal entity a DT inspector audits, and an
 * organization represents one employer. This migration moves the accounting
 * dimension onto `cost_centers` and leaves one company per tenant.
 *
 * Per organization:
 *
 * - The oldest company is retained as the employer. If it carried a `code`,
 *   that code becomes a cost centre named after it, and its employees are
 *   assigned to that cost centre — the accounting bucket the code stood for
 *   survives under its own model.
 * - Every extra company becomes a cost centre carrying its `social_reason` as
 *   the name and its `code`. Its employees keep working: they move onto the
 *   retained company and onto the new cost centre.
 * - The extra company row is then soft-deleted. Nothing is destroyed — every
 *   field survives, including the tenant link and the legal identity (RUT,
 *   razón social, giro, company_type, is_est), so the retired employer stays
 *   auditable. The unique index added by the next migration ignores
 *   soft-deleted rows by construction.
 *
 * Legal representatives (`users.is_legal_rep`) of an extra company are moved to
 * the retained company rather than deleted, so no user row is orphaned behind a
 * company that no longer resolves.
 *
 * Irreversible by design: `down()` cannot know which cost centres were once
 * companies, and re-splitting an employer is not a decision a rollback should
 * make silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        $companiesByOrganization = DB::table('companies')
            ->whereNull('deleted_at')
            ->whereNotNull('organization_id')
            ->orderBy('id')
            ->get(['id', 'organization_id', 'social_reason', 'code'])
            ->groupBy('organization_id');

        foreach ($companiesByOrganization as $organizationId => $companies) {
            $retained = $companies->first();
            $extras = $companies->skip(1);

            if ($retained->code !== null) {
                $costCenterId = $this->createCostCenter(
                    (int) $organizationId,
                    $retained->social_reason,
                    $retained->code,
                );

                DB::table('users')
                    ->where('company_id', $retained->id)
                    ->update(['cost_center_id' => $costCenterId]);
            }

            foreach ($extras as $extra) {
                $costCenterId = $this->createCostCenter(
                    (int) $organizationId,
                    $extra->social_reason,
                    $extra->code,
                );

                DB::table('users')
                    ->where('company_id', $extra->id)
                    ->update([
                        'company_id' => $retained->id,
                        'cost_center_id' => $costCenterId,
                    ]);

                DB::table('premises')
                    ->where('company_id', $extra->id)
                    ->update(['company_id' => $retained->id]);

                DB::table('companies')
                    ->where('id', $extra->id)
                    ->update(['deleted_at' => now()]);
            }
        }
    }

    /**
     * Insert a cost centre, keeping the code only when it is still free within
     * the organization — two companies may legitimately have carried the same
     * code, and the unique index must not abort the whole migration over it.
     */
    private function createCostCenter(int $organizationId, string $name, ?string $code): int
    {
        $codeIsTaken = $code !== null && DB::table('cost_centers')
            ->where('organization_id', $organizationId)
            ->where('code', $code)
            ->exists();

        return (int) DB::table('cost_centers')->insertGetId([
            'organization_id' => $organizationId,
            'name' => $name,
            'code' => $codeIsTaken ? null : $code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
