<?php

namespace App\Support\Imports;

use App\Enums\ImportFieldType;
use App\Enums\ImportStrategy;
use App\Enums\MatchKeyComparator;

/**
 * One importable column an ImportSchema declares (KOL-94.3): its display
 * label, cell type, whether it must be present under {@see ImportStrategy::CreateOnly},
 * whether it resolves against another record rather than holding data
 * directly, and whether/how it can serve as a match key.
 */
final readonly class ImportField
{
    /**
     * @param  bool  $isIdentifierOnly  True for a match key that names the
     *                                  target record itself (e.g. Employee's `id`, the row's own primary
     *                                  key) rather than any of its data — it is never written to
     *                                  resolvedData, regardless of which match key the run is using.
     */
    public function __construct(
        public string $name,
        public string $label,
        public ImportFieldType $type,
        public bool $requiredForCreateOnly = false,
        public bool $isReference = false,
        public bool $isMatchKeyEligible = false,
        public ?MatchKeyComparator $matchKeyComparator = null,
        public bool $isIdentifierOnly = false,
    ) {}
}
