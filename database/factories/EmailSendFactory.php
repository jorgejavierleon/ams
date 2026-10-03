<?php

namespace Database\Factories;

use App\Models\EmailSend;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailSend>
 */
class EmailSendFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
        ];
    }
}
