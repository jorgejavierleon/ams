<?php

return [

    'export' => [

        /*
        |--------------------------------------------------------------------
        | Queue Threshold
        |--------------------------------------------------------------------
        |
        | Exports covering up to this many employees return synchronously as
        | a direct download. Anything larger is dispatched to the queue and
        | delivered by email once ready (KOL-16, PRD §7 performance NFR).
        |
        */

        'queue_threshold' => (int) env('REPORT_EXPORT_QUEUE_THRESHOLD', 500),

        /*
        |--------------------------------------------------------------------
        | Link Expiry
        |--------------------------------------------------------------------
        |
        | How long a queued export's signed download link stays valid after
        | the file is ready, in minutes. The file is pruned once it lapses.
        |
        */

        'link_expiry_minutes' => (int) env('REPORT_EXPORT_LINK_EXPIRY_MINUTES', 1440),

    ],

];
