<?php

namespace App\Models;

use App\Observers\ShiftDayObserver;
use Database\Factories\ShiftDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * @property int $id
 * @property int $shift_id
 * @property int|null $weekday
 * @property Carbon|null $date
 * @property Carbon|null $start_time
 * @property Carbon|null $end_time
 * @property Carbon|null $lunch_start_time
 * @property Carbon|null $lunch_end_time
 * @property float $total_work_hours
 * @property bool $is_free
 * @property-read bool $exceeds_legal_max_hours
 */
#[ObservedBy(ShiftDayObserver::class)]
#[Fillable([
    'weekday',
    'date',
    'start_time',
    'end_time',
    'lunch_start_time',
    'lunch_end_time',
    'is_free',
])]
class ShiftDay extends Model
{
    /** @use HasFactory<ShiftDayFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['exceeds_legal_max_hours'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
            'lunch_start_time' => 'datetime:H:i',
            'lunch_end_time' => 'datetime:H:i',
            'total_work_hours' => 'float',
            'is_free' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Derive the day's worked hours from its times before every save so the
        // stored value always matches the schedule the WorkdayCalculator reads.
        static::saving(function (ShiftDay $shiftDay): void {
            if ($shiftDay->is_free) {
                $shiftDay->total_work_hours = 0;

                return;
            }

            $workedMinutes = $shiftDay->start_time->diffInMinutes($shiftDay->end_time);
            $workedMinutes -= $shiftDay->lunch_start_time->diffInMinutes($shiftDay->lunch_end_time);

            $shiftDay->total_work_hours = $workedMinutes / 60;
        });
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Whether the day's worked hours exceed the legal daily maximum.
     *
     * @return Attribute<bool, never>
     */
    protected function exceedsLegalMaxHours(): Attribute
    {
        return Attribute::get(
            fn (): bool => $this->total_work_hours > Config::get('ams.max_daily_hours'),
        );
    }
}
