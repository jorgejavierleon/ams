<?php

namespace App\Http\Controllers\Settings;

use App\Enums\OvertimeAuthorizationMode;
use App\Http\Controllers\Controller;
use App\Services\OrganizationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeController extends Controller
{
    /**
     * The non-enum, non-boolean policy keys and the rules that guard them. The
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

    public function __construct(
        private OrganizationSettings $organizationSettings,
    ) {}

    public function edit(): Response
    {
        $setting = $this->organizationSettings->current();

        return Inertia::render('settings/overtime', [
            'settings' => [
                'overtime_authorization_mode' => $setting->overtime_authorization_mode->value,
                'overtime_weekly_anomaly_threshold_hours' => (float) $setting->overtime_weekly_anomaly_threshold_hours,
                'overtime_retroactive_request_days' => (int) $setting->overtime_retroactive_request_days,
                'overtime_counts_pre_shift_excess' => (bool) $setting->overtime_counts_pre_shift_excess,
            ],
            'overtimeAuthorizationModeOptions' => OvertimeAuthorizationMode::options(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = $this->organizationSettings->current();

        $data = $request->validate([
            'overtime_authorization_mode' => ['required', Rule::enum(OvertimeAuthorizationMode::class)],
            ...self::VALUE_RULES,
            'overtime_counts_pre_shift_excess' => ['required', 'boolean'],
        ]);

        // Update through Eloquent so SettingObserver fires and the cache clears.
        $setting->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.settings.overtime.flash.updated')]);

        return back();
    }
}
