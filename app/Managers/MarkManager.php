<?php

namespace App\Managers;

use App\Enums\MarkType;
use App\Models\Mark;
use App\Models\ShiftAssignment;
use App\Models\ShiftDay;
use App\Models\User;
use App\Services\TimeZoneService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Creates attendance punches and answers "what should the employee mark next?"
 * questions for the dashboard. Timestamps are resolved in the employee's own
 * timezone; the shift scheduled for that day is snapshotted onto the mark so
 * later workday calculations compare against the schedule that was in force.
 */
class MarkManager
{
    public function __construct(
        protected TimeZoneService $timeZoneService,
    ) {}

    /**
     * Register a punch of the given type for the user (defaults to the
     * authenticated employee), stamped in their timezone and tagged with the
     * shift scheduled for that day.
     */
    public function createMark(MarkType $type, ?User $user = null, ?string $dateTime = null): Mark
    {
        $user = $this->resolveUser($user);
        $when = $this->resolveDateTime($user, $dateTime);
        $shift = $this->getShiftForDate($user, $when);

        return Mark::create([
            'user_id' => $user->id,
            'date_time' => $when,
            'original_date_time' => $when,
            'type' => $type,
            'shift_id' => $shift['shift_id'] ?? null,
            'shift_start_time' => $shift['start_time'] ?? null,
            'shift_end_time' => $shift['end_time'] ?? null,
        ]);
    }

    /**
     * The shift scheduled for the user today, or null when they have no active
     * assignment or the day is free.
     *
     * @return array{shift_id: int, start_time: string, end_time: string}|null
     */
    public function getShiftForToday(?User $user = null): ?array
    {
        $user = $this->resolveUser($user);

        return $this->getShiftForDate($user, $this->resolveDateTime($user, null));
    }

    /**
     * @return array{shift_id: int, start_time: string, end_time: string}|null
     */
    public function getShiftForDate(User $user, CarbonInterface $dateTime): ?array
    {
        $assignment = ShiftAssignment::query()
            ->where('user_id', $user->id)
            ->where('start_date', '<=', $dateTime)
            ->where(function ($query) use ($dateTime) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $dateTime);
            })
            ->first();

        if ($assignment === null) {
            return null;
        }

        // ShiftDay weekdays are 0=Monday … 6=Sunday.
        $shiftDay = ShiftDay::query()
            ->where('shift_id', $assignment->shift_id)
            ->where('weekday', (int) $dateTime->format('N') - 1)
            ->first();

        if ($shiftDay === null || $shiftDay->is_free) {
            return null;
        }

        return [
            'shift_id' => $assignment->shift_id,
            'start_time' => Carbon::parse($shiftDay->start_time)->format('H:i'),
            'end_time' => Carbon::parse($shiftDay->end_time)->format('H:i'),
        ];
    }

    /**
     * The user's existing mark of the given type for today, if any. Backs the
     * one-punch-per-type-per-day guard.
     */
    public function getTodayMark(MarkType $type, ?User $user = null): ?Mark
    {
        $user = $this->resolveUser($user);
        $timezone = $this->timeZoneService->getUserTimezone($user);

        return Mark::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->whereDate('date_time', Carbon::now($timezone))
            ->first();
    }

    private function resolveUser(?User $user): User
    {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            throw new RuntimeException('A user is required to register a mark.');
        }

        return $user;
    }

    private function resolveDateTime(User $user, ?string $dateTime): CarbonInterface
    {
        if ($dateTime !== null) {
            return Carbon::parse($dateTime);
        }

        return Carbon::now($this->timeZoneService->getUserTimezone($user));
    }
}
