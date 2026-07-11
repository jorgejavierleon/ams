<?php

namespace Database\Seeders;

use App\Enums\LeaveHalfDayType;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Leave;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class LeaveSeeder extends Seeder
{
    /**
     * Seed a spread of leave requests for the demo employees so the Leaves list,
     * its filters and the approve/reject actions all have data to exercise, plus
     * an approved vacation per-employee so the vacation-balance card is populated.
     *
     * DatabaseSeeder runs with WithoutModelEvents, so the LeaveObserver does not
     * fire here — created_by, company_id, the medical auto-approval and the
     * approver stamp are therefore written explicitly, and vacation_days is
     * decremented by hand to mirror what LeaveManager::approve() would have done.
     */
    public function run(): void
    {
        $organization = Organization::query()
            ->where('slug', 'demo-organization')
            ->first();

        if ($organization === null) {
            return;
        }

        $admin = User::query()
            ->where('organization_id', $organization->id)
            ->role('admin')
            ->first();

        $employees = User::query()
            ->employees()
            ->where('organization_id', $organization->id)
            ->orderBy('id')
            ->get();

        if ($admin === null || $employees->isEmpty()) {
            return;
        }

        foreach ($employees as $index => $employee) {
            // Every employee has one approved vacation in the recent past so the
            // vacation-balance card always shows some usage.
            $this->approvedVacation($organization, $admin, $employee);

            // Then a rotating mix of the other states so the list and its status
            // filter have pending, rejected, half-day and medical rows to show.
            match ($index % 4) {
                0 => $this->pendingVacation($organization, $admin, $employee),
                1 => $this->pendingHalfDay($organization, $admin, $employee),
                2 => $this->rejectedUnpaid($organization, $admin, $employee),
                default => $this->approvedMedical($organization, $admin, $employee),
            };
        }
    }

    private function approvedVacation(Organization $organization, User $admin, User $employee): void
    {
        $days = 3;

        Leave::factory()->approved()->create([
            'organization_id' => $organization->id,
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'type' => LeaveType::Vacation,
            'start_date' => Carbon::now()->subMonth()->next(Carbon::MONDAY)->toDateString(),
            'end_date' => Carbon::now()->subMonth()->next(Carbon::MONDAY)->addDays(2)->toDateString(),
            'business_days_requested' => $days,
            'approved_by' => $admin->id,
            'created_by' => $admin->id,
        ]);

        // Mirror LeaveManager::approve(): the balance was already spent.
        $employee->decrement('vacation_days', $days);
    }

    private function pendingVacation(Organization $organization, User $admin, User $employee): void
    {
        Leave::factory()->pending()->create([
            'organization_id' => $organization->id,
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'type' => LeaveType::Vacation,
            'start_date' => Carbon::now()->addWeek()->next(Carbon::MONDAY)->toDateString(),
            'end_date' => Carbon::now()->addWeek()->next(Carbon::MONDAY)->addDays(4)->toDateString(),
            'business_days_requested' => 5,
            'created_by' => $admin->id,
            'notes' => 'Vacaciones familiares.',
        ]);
    }

    private function pendingHalfDay(Organization $organization, User $admin, User $employee): void
    {
        $day = Carbon::now()->addDays(3)->next(Carbon::WEDNESDAY);

        Leave::factory()->pending()->create([
            'organization_id' => $organization->id,
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'type' => LeaveType::Paid,
            'start_date' => $day->toDateString(),
            'end_date' => $day->toDateString(),
            'half_day' => true,
            'half_day_type' => LeaveHalfDayType::Afternoon,
            'business_days_requested' => 0.5,
            'created_by' => $admin->id,
            'notes' => 'Trámite personal.',
        ]);
    }

    private function rejectedUnpaid(Organization $organization, User $admin, User $employee): void
    {
        Leave::factory()->create([
            'organization_id' => $organization->id,
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'type' => LeaveType::Unpaid,
            'status' => LeaveStatus::Rejected,
            'start_date' => Carbon::now()->subWeeks(2)->next(Carbon::MONDAY)->toDateString(),
            'end_date' => Carbon::now()->subWeeks(2)->next(Carbon::MONDAY)->addDay()->toDateString(),
            'business_days_requested' => 2,
            'created_by' => $admin->id,
        ]);
    }

    private function approvedMedical(Organization $organization, User $admin, User $employee): void
    {
        $start = Carbon::now()->subWeek()->next(Carbon::MONDAY);

        Leave::factory()->medical()->create([
            'organization_id' => $organization->id,
            'company_id' => $employee->company_id,
            'user_id' => $employee->id,
            'status' => LeaveStatus::Approved,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(2)->toDateString(),
            'business_days_requested' => 3,
            'approved_by' => $admin->id,
            'created_by' => $admin->id,
        ]);
    }
}
