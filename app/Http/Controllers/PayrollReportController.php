<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PayrollReportController extends Controller
{
    /**
     * The payroll reports section's landing page (KOL-18). This is the
     * container the five RF-1 reports (KOL-20..24) will list themselves in —
     * it renders them as "coming soon" until each lands. Distinct from the DT
     * inspector's compliance reports (`dt.reports.*`): this section is for
     * tenant users (RRHH/admin) and gated by `View:PayrollReport`, not the DT
     * guard.
     */
    public function index(): Response
    {
        return Inertia::render('payroll-reports/index', [
            'reportTypes' => [
                'payroll-summary',
                'weekly-detail',
                'period-movements',
                'employee-master',
                'overtime-excess',
            ],
        ]);
    }
}
