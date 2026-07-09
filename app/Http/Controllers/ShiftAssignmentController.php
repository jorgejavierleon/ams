<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Rules\NoOverlappingShift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ShiftAssignmentController extends Controller
{
    /**
     * Assign a shift to the employee for a date range.
     */
    public function store(Request $request, User $employee): RedirectResponse
    {
        $this->assertEmployee($employee);

        $data = $this->validateAssignment($request, $employee);

        // A null end date means the assignment runs indefinitely (permanent).
        $employee->shiftAssignments()->create([
            'shift_id' => $data['shift_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'is_permanent' => empty($data['end_date']),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.shifts.shift_assignments.flash.created')]);

        return back();
    }

    /**
     * End an active assignment by setting its end date to today.
     */
    public function end(ShiftAssignment $shiftAssignment): RedirectResponse
    {
        // Setting an end date only shrinks the range, so no overlap check is
        // needed; the update fires the observer to recalculate workdays.
        $shiftAssignment->update([
            'end_date' => now()->toDateString(),
            'is_permanent' => false,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.shifts.shift_assignments.flash.ended')]);

        return back();
    }

    /**
     * Remove an assignment.
     */
    public function destroy(ShiftAssignment $shiftAssignment): RedirectResponse
    {
        $shiftAssignment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.shifts.shift_assignments.flash.deleted')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAssignment(Request $request, User $employee): array
    {
        return $request->validate([
            'shift_id' => [
                'required', 'integer',
                Rule::exists('shifts', 'id')->where('organization_id', Company::currentOrganizationId()),
            ],
            'start_date' => ['required', 'date', new NoOverlappingShift($employee->id)],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
    }

    /**
     * Ensure the bound user is an employee of the current organization; the
     * User model carries no global org scope, so guard route-model binding.
     */
    private function assertEmployee(User $employee): void
    {
        abort_unless(
            $employee->organization_id === Company::currentOrganizationId()
                && ! $employee->is_legal_rep
                && $employee->hasRole('employee'),
            404,
        );
    }
}
