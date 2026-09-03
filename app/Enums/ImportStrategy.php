<?php

namespace App\Enums;

/**
 * What an ImportRun (KOL-94) is allowed to do with matched/unmatched rows.
 * Under UpdateOnly and CreateAndUpdate, a blank cell in a non-match-key
 * column means no change to the existing value, never a clear-to-null.
 */
enum ImportStrategy: string
{
    case CreateOnly = 'create_only';
    case UpdateOnly = 'update_only';
    case CreateAndUpdate = 'create_and_update';

    /**
     * Whether this strategy ever looks up an existing record via a match key.
     * CreateOnly never matches — every row is a fresh insert, so ID as a
     * match key has no effect under it (KOL-94.2).
     */
    public function allowsMatching(): bool
    {
        return $this !== self::CreateOnly;
    }
}
