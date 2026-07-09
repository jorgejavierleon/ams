<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Observers\ShiftAssignmentObserver;
use Database\Factories\ShiftAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Links an employee to a shift for a date range. An employee can hold several
 * assignments over time; an assignment with a null `end_date` is permanent.
 * The observer fires workday recalculation whenever the range changes.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $shift_id
 * @property int $user_id
 * @property Carbon|null $notification_date
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property bool $is_permanent
 * @property bool $requested_by_employee
 */
#[ObservedBy(ShiftAssignmentObserver::class)]
#[Fillable([
    'shift_id',
    'user_id',
    'notification_date',
    'start_date',
    'end_date',
    'is_permanent',
    'requested_by_employee',
])]
class ShiftAssignment extends Model
{
    /** @use HasFactory<ShiftAssignmentFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notification_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_permanent' => 'boolean',
            'requested_by_employee' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
