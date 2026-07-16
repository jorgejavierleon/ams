<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            // Bind the employee to the same organization the document lands in.
            'user_id' => fn (array $attributes): int => User::factory()
                ->employee()
                ->create(['organization_id' => $attributes['organization_id']])
                ->id,
            'title' => fake()->sentence(4),
            'type' => fake()->randomElement(DocumentType::cases()),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'status' => DocumentStatus::Draft,
            'legal_rep_signatories' => 0,
            'ordered_signing' => false,
            'published_at' => null,
            'signed_at' => null,
        ];
    }

    /**
     * A published document with a frozen (already rendered) body.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocumentStatus::Published,
            'published_at' => now(),
        ]);
    }
}
