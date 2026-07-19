<?php

namespace App\Http\Controllers;

use App\Services\OrganizationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(
        private OrganizationSettings $organizationSettings,
    ) {}

    /**
     * The keys the settings form manages, all boolean toggles. Kept in one
     * place so the render, validation and update stay in lockstep.
     *
     * @var list<string>
     */
    private const BOOLEAN_KEYS = [
        'employee_missing_in_notification',
        'employee_missing_out_notification',
        'employer_missing_in_notification',
        'employer_missing_out_notification',
        'leave_approval_notification',
        'documents_signature_enabled',
        'documents_require_ordered_signing',
    ];

    public function index(): Response
    {
        $setting = $this->organizationSettings->current();

        return Inertia::render('organization-settings', [
            'settings' => collect(self::BOOLEAN_KEYS)
                ->mapWithKeys(fn (string $key): array => [$key => (bool) $setting->getAttribute($key)])
                ->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = $this->organizationSettings->current();

        $data = $request->validate(
            collect(self::BOOLEAN_KEYS)
                ->mapWithKeys(fn (string $key): array => [$key => ['required', 'boolean']])
                ->all(),
        );

        // Update through Eloquent so SettingObserver fires and the cache clears.
        $setting->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.organization_settings.flash.updated')]);

        return back();
    }
}
