<?php

namespace App\Models;

use App\Enums\ShiftType;
use App\Models\Concerns\BelongsToOrganization;
use App\Observers\ShiftObserver;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property ShiftType $type
 * @property string $name
 * @property string|null $description
 * @property string|null $tolerance_in
 * @property string|null $tolerance_out
 * @property bool $work_on_holidays
 * @property bool $is_archive
 * @property bool $is_default
 * @property float|null $total_week_hours
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[ObservedBy(ShiftObserver::class)]
#[Fillable([
    'type',
    'name',
    'description',
    'tolerance_in',
    'tolerance_out',
    'work_on_holidays',
    'is_archive',
    'is_default',
])]
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        // Only one shift per organization may be the default.
        static::saved(function (Shift $shift): void {
            if ($shift->is_default) {
                static::query()
                    ->whereKeyNot($shift->id)
                    ->where('organization_id', $shift->organization_id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ShiftType::class,
            'work_on_holidays' => 'boolean',
            'is_archive' => 'boolean',
            'is_default' => 'boolean',
            'total_week_hours' => 'float',
        ];
    }

    /**
     * @return HasMany<ShiftDay, $this>
     */
    public function days(): HasMany
    {
        return $this->hasMany(ShiftDay::class);
    }

    /**
     * @return HasMany<ShiftAssignment, $this>
     */
    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    /**
     * Assignments that are currently in effect (started, not yet ended).
     *
     * @return HasMany<ShiftAssignment, $this>
     */
    public function activeShiftAssignments(): HasMany
    {
        return $this->shiftAssignments()
            ->whereDate('start_date', '<=', now())
            ->where(fn ($query) => $query
                ->whereNull('end_date')
                ->orWhereDate('end_date', '>=', now()));
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shift_assignments', 'shift_id', 'user_id');
    }
}
