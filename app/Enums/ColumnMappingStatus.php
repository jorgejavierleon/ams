<?php

namespace App\Enums;

/**
 * Whether one uploaded-file column has been paired with an ImportSchema
 * field (KOL-94.3): Mapped, Unmapped (needs a manual pick), or Ignored
 * (deliberately excluded from the import).
 */
enum ColumnMappingStatus: string
{
    case Mapped = 'mapped';
    case Unmapped = 'unmapped';
    case Ignored = 'ignored';
}
