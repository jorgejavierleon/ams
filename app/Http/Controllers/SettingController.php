<?php

namespace App\Http\Controllers;

use App\Enums\OvertimeAuthorizationMode;
use App\Enums\OvertimeCompensationType;
use App\Services\OrganizationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function __construct(
        private OrganizationSettings $organizationSettings,
    ) {}

    /**
     * The boolean toggles the settings form manages. Kept in one place so the
     * render, validation and update stay in lockstep.
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
        'overtime_requires_pact',
    ];

    /**
     * The non-boolean overtime policy keys and the rules that guard them. The
     * weekly threshold is capped well above any legal maximum on purpose: it is
     * an anomaly signal, not a cap, and a tenant running critical shifts may
     * legitimately want it high.
     *
     * @var array<string, list<mixed>>
     */
    private const VALUE_RULES = [
        'overtime_weekly_anomaly_threshold_hours' => ['required', 'numeric', 'min:0', 'max:168'],
        'overtime_retroactive_request_days' => ['required', 'integer', 'min:0', 'max:365'],
    ];

    public function index(): Response
    {
        $setting = $this->organizationSettings->current();

        return Inertia::render('organization-settings', [
            'settings' => [
                ...collect(self::BOOLEAN_KEYS)
                    ->mapWithKeys(fn (string $key): array => [$key => (bool) $setting->getAttribute($key)])
                    ->all(),
                'overtime_authorization_mode' => $setting->overtime_authorization_mode->value,
                'overtime_weekly_anomaly_threshold_hours' => (float) $setting->overtime_weekly_anomaly_threshold_hours,
                'overtime_retroactive_request_days' => (int) $setting->overtime_retroactive_request_days,
                'overtime_default_compensation_type' => $setting->overtime_default_compensation_type->value,
            ],
            'overtimeAuthorizationModeOptions' => OvertimeAuthorizationMode::options(),
            'overtimeCompensationTypeOptions' => OvertimeCompensationType::options(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = $this->organizationSettings->current();

        $data = $request->validate([
            ...collect(self::BOOLEAN_KEYS)
                ->mapWithKeys(fn (string $key): array => [$key => ['required', 'boolean']])
                ->all(),
            ...self::VALUE_RULES,
            'overtime_authorization_mode' => ['required', Rule::enum(OvertimeAuthorizationMode::class)],
            'overtime_default_compensation_type' => ['required', Rule::enum(OvertimeCompensationType::class)],
        ]);

        // Update through Eloquent so SettingObserver fires and the cache clears.
        $setting->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.organization_settings.flash.updated')]);

        return back();
    }
}
