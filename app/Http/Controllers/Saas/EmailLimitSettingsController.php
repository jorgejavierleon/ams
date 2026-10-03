<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform-wide "expected emails per user per month" baseline (KOL-137.2),
 * used to default every organization's monthly email limits unless a figure
 * was overridden on {@see OrganizationController}.
 */
class EmailLimitSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('saas/email-limit-settings/edit', [
            'baseline' => PlatformSetting::current()->expected_emails_per_user_per_month,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'expected_emails_per_user_per_month' => ['required', 'integer', 'min:1'],
        ]);

        PlatformSetting::current()->update($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.saas_email_limit_settings.flash.updated'),
        ]);

        return to_route('saas.email-limit-settings.edit');
    }
}
