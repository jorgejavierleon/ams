<?php

namespace App\Support;

/**
 * The bulk employee selection RF-7 requires (KOL-19 AC #3): Talana's
 * exclusion pattern rather than a plain checked-id list, so "todos los de la
 * sucursal Centro excepto Juan" survives the filter set changing.
 *
 * `selectAll: false` with an empty `ids` is a deliberate, explicit "nothing
 * selected" (AC #7) — it must never resolve to "every employee".
 *
 * - `selectAll: true` — every employee matching {@see ReportEmployeeFilters},
 *   minus `ids` (the exclusions).
 * - `selectAll: false` — exactly the employees named in `ids` (a manual pick,
 *   independent of the filters).
 */
final readonly class EmployeeSelection
{
    /**
     * @param  list<int>  $ids
     */
    public function __construct(
        public bool $selectAll,
        public array $ids = [],
    ) {}
}
