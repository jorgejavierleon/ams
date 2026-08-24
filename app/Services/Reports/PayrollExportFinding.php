<?php

namespace App\Services\Reports;

use App\Enums\PayrollExportFindingType;
use Carbon\CarbonInterface;

/**
 * One unresolved item standing between an employee/period selection and a
 * trustworthy payroll export (KOL-14, PRD RF-2): which employee, which day,
 * why, and where to go fix it.
 *
 * `userId`/`date` are null for a period-level finding (an open technical
 * incident isn't tied to one employee or day).
 */
final readonly class PayrollExportFinding
{
    public function __construct(
        public PayrollExportFindingType $type,
        public ?int $userId,
        public ?CarbonInterface $date,
        public string $reason,
        public ?string $resolutionUrl,
    ) {}

    public function blocking(): bool
    {
        return $this->type->blocking();
    }
}
