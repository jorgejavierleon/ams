<?php

namespace App\Services\Overtime;

use App\Enums\OvertimeDayType;
use App\Models\OvertimeAuthorization;
use App\Models\OvertimeRestDayBalance;
use App\Support\Duration;
use Carbon\CarbonInterface;

/**
 * One payable overtime line for the payroll export (PRD §7.7, KOL-49): an
 * approved hour with the audit trail an accountant needs to justify it — RUT,
 * date, hours, day type, the pacto reference when one applies, the approver
 * and the approval timestamp.
 *
 * Only {@see OvertimeExportDataset} builds these, from either an
 * {@see OvertimeAuthorization} satisfying
 * {@see OvertimeAuthorization::scopeExportable()} or an expired,
 * unconsumed {@see OvertimeRestDayBalance} remainder — the two
 * sources KOL-47 requires this export to union. There is no other
 * constructor and no setter: a line, once built, cannot be mutated into one a
 * pending or revoked record could have produced.
 */
final readonly class OvertimeExportLine
{
    public function __construct(
        public int $userId,
        public ?string $employeeRut,
        public CarbonInterface $date,
        public Duration $hours,
        public OvertimeDayType $dayType,
        public ?int $pactReference,
        public int $approvedBy,
        public CarbonInterface $approvedAt,
    ) {}
}
