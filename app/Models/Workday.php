<?php

namespace App\Models;

use App\Enums\MarkModificationStatus;
use App\Enums\WorkdayStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Services\WorkdayCalculator;
use Carbon\CarbonInterface;
use Database\Factories\WorkdayFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One computed day of attendance for an employee: the daily roll-up of that
 * day's marks, scheduled shift and approved leave into a status and the worked,
 * extra and missing time. Rows are produced by {@see WorkdayCalculator}.
 *
 * @property int $id
 * @property Carbon $date
 * @property int $user_id
 * @property int|null $organization_id
 * @property int|null $company_id
 * @property int|null $premise_id
 * @property int|null $mark_in_id
 * @property int|null $mark_out_id
 * @property int|null $leave_id
 * @property int|null $shift_id
 * @property Carbon|null $mark_in_at
 * @property Carbon|null $mark_out_at
 * @property string|null $shift_start_time
 * @property string|null $shift_end_time
 * @property string|null $in_time_difference
 * @property string|null $out_time_difference
 * @property string|null $worked_time
 * @property string|null $extra_time
 * @property string|null $missing_time
 * @property WorkdayStatus|null $status
 */
class Workday extends Model
{
    /** @use HasFactory<WorkdayFactory> */
    use BelongsToOrganization, HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkdayStatus::class,
            'date' => 'date',
            'mark_in_at' => 'datetime',
            'mark_out_at' => 'datetime',
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
     * @return BelongsTo<Premise, $this>
     */
    public function premise(): BelongsTo
    {
        return $this->belongsTo(Premise::class);
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<Mark, $this>
     */
    public function markIn(): BelongsTo
    {
        return $this->belongsTo(Mark::class, 'mark_in_id');
    }

    /**
     * @return BelongsTo<Mark, $this>
     */
    public function markOut(): BelongsTo
    {
        return $this->belongsTo(Mark::class, 'mark_out_id');
    }

    /**
     * @return BelongsTo<Leave, $this>
     */
    public function leave(): BelongsTo
    {
        return $this->belongsTo(Leave::class);
    }

    /**
     * @return HasMany<MarkModification, $this>
     */
    public function markModifications(): HasMany
    {
        return $this->hasMany(MarkModification::class);
    }

    /**
     * @return HasMany<MarkModification, $this>
     */
    public function pendingMarkModifications(): HasMany
    {
        return $this->markModifications()
            ->where('status', MarkModificationStatus::Pending);
    }

    /**
     * Constrain to workdays whose date falls within the inclusive range.
     *
     * @param  Builder<Workday>  $query
     */
    public function scopeBetweenDates(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }
}
