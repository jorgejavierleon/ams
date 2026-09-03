<?php

namespace App\Enums;

/**
 * The lifecycle of a bulk data ImportRun (KOL-94): a server-driven wizard
 * moves one row through upload, mapping review, preview, and a queued
 * commit, mirroring {@see ReportExportStatus}'s shape.
 */
enum ImportRunStatus: string
{
    case Pending = 'pending';
    case MappingReview = 'mapping_review';
    case PreviewReady = 'preview_ready';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
