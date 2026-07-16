<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentTemplate>
 */
class DocumentTemplateFactory extends Factory
{
    protected $model = DocumentTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'title' => fake()->sentence(3),
            'type' => fake()->randomElement(DocumentType::cases()),
            'body' => '<p>'.fake()->paragraph().'</p>',
        ];
    }
}
