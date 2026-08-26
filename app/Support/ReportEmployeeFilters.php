<?php

namespace App\Support;

use App\Enums\ContractType;

/**
 * The employee-pool filter dimensions shared by every payroll report (RF-7,
 * KOL-19 AC #1): sucursal, centro de costo, cargo and tipo de contrato.
 *
 * Company is deliberately not a dimension here: KOL-32 constrained every
 * organization to exactly one company, so organization scoping already
 * implies a single company — the PRD's "multi-company tenant" assumption
 * predates that change (tracked separately by KOL-29).
 */
final readonly class ReportEmployeeFilters
{
    /**
     * @param  list<int>  $premiseIds
     * @param  list<int>  $costCenterIds
     * @param  list<int>  $positionIds
     * @param  list<ContractType>  $contractTypes
     */
    public function __construct(
        public array $premiseIds = [],
        public array $costCenterIds = [],
        public array $positionIds = [],
        public array $contractTypes = [],
    ) {}
}
