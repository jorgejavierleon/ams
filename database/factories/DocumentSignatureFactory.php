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
}
