<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Services\Overtime\RestDayBalanceService;
use Database\Factories\OvertimeRestDayConsumptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One decrement against an {@see OvertimeRestDayBalance} line (KOL-47 AC #2):
 * the ledger entry that makes "why is this balance lower than it was
 * accrued" answerable from the data rather than only from a running total.
 *
 * Registered by HR/admin today via {@see RestDayBalanceService::consume()};
 * `registered_by` stays nullable so a future self-service request flow
 * (Código del Trabajo art. 32 §4's 48-hour notice requirement) is not blocked
 * by this schema.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int $overtime_rest_day_balance_id
 * @property string $hours
 * @property Carbon $consumed_on
 * @property string|null $note
 * @property int|null $registered_by
 */
class OvertimeRestDayConsumption extends Model
{
    /** @use HasFactory<OvertimeRestDayConsumptionFactory> */
    use BelongsToOrganization, HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consumed_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<OvertimeRestDayBalance, $this>
     */
    public function balance(): BelongsTo
    {
        return $this->belongsTo(OvertimeRestDayBalance::class, 'overtime_rest_day_balance_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
