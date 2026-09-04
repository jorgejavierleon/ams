<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sync Preview Threshold
    |--------------------------------------------------------------------------
    |
    | An uploaded file with more data rows than this is rejected immediately
    | at upload time (KOL-94.1/94.5) — never queued, never partially
    | previewed. Split by format rather than one flat number (unlike
    | config/reports.php's single export.queue_threshold) because Xlsx's
    | full-DOM parse (KOL-94.1) is materially more expensive per row than
    | CSV's line-by-line stream, for the same synchronous request budget.
    |
    */

    'sync_preview_threshold' => [
        'csv' => (int) env('IMPORT_SYNC_PREVIEW_THRESHOLD_CSV', 5000),
        'excel' => (int) env('IMPORT_SYNC_PREVIEW_THRESHOLD_EXCEL', 2000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Expiry
    |--------------------------------------------------------------------------
    |
    | How long an ImportRun (and its uploaded file) lives before it's eligible
    | for pruning (KOL-94.4), set once at creation — a flat expiry, not a
    | sliding window, mirroring config/reports.php's export link expiry.
    |
    */

    'expiry_hours' => (int) env('IMPORT_EXPIRY_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Commit Chunk Size
    |--------------------------------------------------------------------------
    |
    | How many data rows ProcessImportRun (KOL-94.4, KOL-102) upserts per
    | DB::transaction() during the commit pass. Each chunk's row writes and
    | its committed_through/count updates land in one transaction, so this
    | is also the most work a retry can ever redo.
    |
    */

    'commit_chunk_size' => (int) env('IMPORT_COMMIT_CHUNK_SIZE', 200),

];
