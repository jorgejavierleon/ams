<?php

namespace Database\Factories;

use App\Enums\OvertimeAuthorizationMode;
use App\Enums\OvertimeCompensationType;
use App\Models\Organization;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'employee_missing_in_notification' => true,
            'employee_missing_out_notification' => true,
            'employer_missing_in_notification' => true,
            'employer_missing_out_notification' => true,
            'leave_approval_notification' => true,
            'documents_signature_enabled' => false,
            'documents_require_ordered_signing' => false,
            'overtime_authorization_mode' => OvertimeAuthorizationMode::PostHoc,
            'overtime_requires_pact' => false,
            'overtime_weekly_anomaly_threshold_hours' => 10,
            'overtime_retroactive_request_days' => 7,
            'overtime_default_compensation_type' => OvertimeCompensationType::Payment,
        ];
    }
}
