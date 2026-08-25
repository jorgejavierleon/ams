<?php

namespace App\Enums;

/**
 * The lifecycle of a queued report export (KOL-16): a job renders it in the
 * background and the requester is notified once it lands in Ready or Failed.
 */
enum ReportExportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
