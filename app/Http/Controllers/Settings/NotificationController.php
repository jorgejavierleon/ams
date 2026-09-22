<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\OrganizationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * @var list<string>
     */
    private const BOOLEAN_KEYS = [
        'employee_missing_in_notification',
        'employee_missing_out_notification',
        'employer_missing_in_notification',
        'employer_missing_out_notification',
        'leave_approval_notification',
    ];

    public function __construct(
        private OrganizationSettings $organizationSettings,
    ) {}

    public function edit(): Response
    {
        $setting = $this->organizationSettings->current();

        return Inertia::render('settings/notifications', [
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

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.settings.notifications.flash.updated')]);

        return back();
    }
}
