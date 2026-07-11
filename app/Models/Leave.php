<?php

namespace App\Models;

use App\Enums\LeaveHalfDayType;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Models\Concerns\BelongsToOrganization;
use App\Observers\LeaveObserver;
use Database\Factories\LeaveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An employee time-off request (vacation, medical, unpaid, etc.) over a date
 * range. Approving a vacation leave deducts `business_days_requested` from the
 * employee's balance; the observer fires workday recalculation on status or
 * range changes.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $company_id
 * @property int $user_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property bool $half_day
 * @property LeaveHalfDayType|null $half_day_type
 * @property float $business_days_requested
 * @property LeaveStatus $status
 * @property LeaveType $type
 * @property string|null $medical_leave_number
 * @property string|null $medical_leave_doctor
 * @property string|null $notes
 * @property int|null $approved_by
 * @property int $created_by
 */
#[ObservedBy(LeaveObserver::class)]
#[Fillable([
    'company_id',
    'user_id',
    'start_date',
    'end_date',
    'half_day',
    'half_day_type',
    'business_days_requested',
    'status',
    'type',
    'medical_leave_number',
    'medical_leave_doctor',
    'notes',
    'approved_by',
    'created_by',
])]
class Leave extends Model
{
    /** @use HasFactory<LeaveFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeaveStatus::class,
            'type' => LeaveType::class,
            'half_day' => 'boolean',
            'half_day_type' => LeaveHalfDayType::class,
            'business_days_requested' => 'float',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @param  Builder<Leave>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', LeaveStatus::Pending);
    }
}
