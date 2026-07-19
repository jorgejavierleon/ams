<?php

namespace Database\Factories;

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
        ];
    }
}
