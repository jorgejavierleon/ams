<?php

namespace Database\Factories;

use App\Enums\DocumentSignatureStatus;
use App\Enums\DocumentSignatureType;
use App\Models\Document;
use App\Models\DocumentSignature;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentSignature>
 */
class DocumentSignatureFactory extends Factory
{
    protected $model = DocumentSignature::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $document = Document::factory();

        return [
            'organization_id' => $document,
            'document_id' => $document,
            'user_id' => User::factory()->employee(),
            'type' => DocumentSignatureType::Employee,
            'status' => DocumentSignatureStatus::Pending,
            'order' => null,
            'signed_at' => null,
        ];
    }

    /**
     * A pending signature carrying a live verification code, ready to be signed.
     */
    public function withCode(string $code = '123456'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocumentSignatureStatus::Pending,
            'verification_code' => $code,
            'verification_code_expires_at' => now()->addMinutes(15),
        ]);
    }

    /**
     * An already-signed signature with its FES evidence recorded.
     */
    public function signed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocumentSignatureStatus::Signed,
            'signed_at' => now(),
            'signed_ip' => fake()->ipv4(),
            'signed_user_agent' => fake()->userAgent(),
            'signed_content_hash' => hash('sha256', 'signed'),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocumentSignatureStatus::Rejected,
        ]);
    }
}
