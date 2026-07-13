<?php

namespace App\Models;

use App\Enums\MarkType;
use App\Models\Concerns\BelongsToOrganization;
use App\Observers\MarkObserver;
use Database\Factories\MarkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A single attendance punch: an employee entering (IN) or leaving (OUT) work.
 * Marks are the raw record behind the electronic attendance book required by
 * Chilean labor law (Resolución 38): each one carries an immutable legal
 * snapshot and a SHA-256 checksum stamped by {@see MarkObserver} at creation.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $company_id
 * @property int|null $user_id
 * @property int|null $premise_id
 * @property int|null $shift_id
 * @property Carbon|null $original_date_time
 * @property Carbon $date_time
 * @property Carbon|null $shift_start_time
 * @property Carbon|null $shift_end_time
 * @property MarkType $type
 * @property string|null $employee_rut
 * @property string|null $employee_name
 * @property string|null $employer_rut
 * @property string|null $employer_name
 * @property string|null $premise_name
 * @property string|null $premise_address
 * @property string|null $address
 * @property float|null $lat
 * @property float|null $lng
 * @property string $checksum
 */
#[ObservedBy(MarkObserver::class)]
#[Fillable([
    'company_id',
    'user_id',
    'premise_id',
    'shift_id',
    'original_date_time',
    'date_time',
    'shift_start_time',
    'shift_end_time',
    'type',
    'lat',
    'lng',
])]
class Mark extends Model
{
    /** @use HasFactory<MarkFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MarkType::class,
            'date_time' => 'datetime',
            'original_date_time' => 'datetime',
            'shift_start_time' => 'datetime:H:i',
            'shift_end_time' => 'datetime:H:i',
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
}
