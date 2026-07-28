<?php

namespace Database\Seeders;

use App\Enums\DocumentSignatureStatus;
use App\Enums\DocumentSignatureType;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Models\Document;
use App\Models\DocumentSignature;
use App\Models\DocumentTemplate;
use App\Models\Organization;
use App\Models\User;
use App\Observers\DocumentObserver;
use App\Services\Documents\DocumentVariableResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DocumentSeeder extends Seeder
{
    /**
     * Seed a spread of employment documents for the demo employees so the
     * Documents list, its status/type/employee filters and the signature flow
     * all have data to exercise. Every employee gets a published certificate and
     * one rotating document in a different state (draft, out for signature, or
     * fully signed) so each status and a variety of types are represented.
     *
     * DatabaseSeeder runs with WithoutModelEvents, so the DocumentObserver does
     * not fire here — publish never freezes the body automatically. Bodies for
     * published and signed documents are therefore resolved by hand with the
     * {@see DocumentVariableResolver} (mirroring what publishing would do) while
     * drafts keep their raw `{{variable}}` placeholders.
     */
    public function run(): void
    {
        $organization = Organization::query()
            ->where('slug', 'demo-organization')
            ->first();

        if ($organization === null) {
            return;
        }

        $employees = User::query()
            ->employees()
            ->where('organization_id', $organization->id)
            ->where('is_legal_rep', false)
            ->orderBy('id')
            ->get();

        $legalRep = User::query()
            ->where('organization_id', $organization->id)
            ->where('is_legal_rep', true)
            ->orderBy('id')
            ->first();

        $templates = DocumentTemplate::query()
            ->where('organization_id', $organization->id)
            ->get()
            ->keyBy(fn (DocumentTemplate $template): string => $template->type->value);

        if ($employees->isEmpty() || $templates->isEmpty()) {
            return;
        }

        foreach ($employees as $index => $employee) {
            // Every employee has a published certificate so the list is never
            // empty and the "published" status always has rows to show.
            $this->publishedCertificate($organization, $employee, $templates);

            // Then a rotating second document so the list and its status filter
            // have draft, pending-signature and signed rows across the board.
            match ($index % 3) {
                0 => $this->draftContract($organization, $employee, $templates),
                1 => $this->pendingContract($organization, $employee, $legalRep, $templates),
                default => $this->signedAnnex($organization, $employee, $legalRep, $templates),
            };
        }
    }

    /**
     * A published, informational certificate with its body already frozen.
     *
     * @param  Collection<string, DocumentTemplate>  $templates
     */
    private function publishedCertificate(Organization $organization, User $employee, Collection $templates): void
    {
        $template = $templates->get(DocumentType::Certificates->value);

        if ($template === null) {
            return;
        }

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $employee->id,
            'title' => 'Certificado de Antigüedad Laboral',
            'type' => DocumentType::Certificates,
            'body' => $template->body,
            'status' => DocumentStatus::Published,
            'published_at' => Carbon::now()->subDays(20),
        ]);

        $this->freezeBody($document);
    }

    /**
     * A draft contract keeping its raw placeholders, ready to be published.
     *
     * @param  Collection<string, DocumentTemplate>  $templates
     */
    private function draftContract(Organization $organization, User $employee, Collection $templates): void
    {
        $template = $templates->get(DocumentType::Contracts->value);

        if ($template === null) {
            return;
        }

        Document::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $employee->id,
            'title' => 'Contrato de Trabajo Indefinido',
            'type' => DocumentType::Contracts,
            'body' => $template->body,
            'status' => DocumentStatus::Draft,
            'legal_rep_signatories' => 1,
        ]);
    }

    /**
     * A published contract out for signature: the employee and one legal
     * representative both still have a pending signature.
     *
     * @param  Collection<string, DocumentTemplate>  $templates
     */
    private function pendingContract(Organization $organization, User $employee, ?User $legalRep, Collection $templates): void
    {
        $template = $templates->get(DocumentType::Contracts->value);

        if ($template === null) {
            return;
        }

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $employee->id,
            'title' => 'Contrato de Trabajo a Plazo Fijo',
            'type' => DocumentType::Contracts,
            'body' => $template->body,
            'status' => DocumentStatus::PendingSignature,
            'legal_rep_signatories' => $legalRep === null ? 0 : 1,
            'published_at' => Carbon::now()->subDays(5),
        ]);

        $this->freezeBody($document);

        $this->signatureFor($document, $employee, DocumentSignatureType::Employee);

        if ($legalRep !== null) {
            $this->signatureFor($document, $legalRep, DocumentSignatureType::LegalRep);
        }
    }

    /**
     * A fully-signed annex: every signatory has signed and the document carries
     * its signed date.
     *
     * @param  Collection<string, DocumentTemplate>  $templates
     */
    private function signedAnnex(Organization $organization, User $employee, ?User $legalRep, Collection $templates): void
    {
        $template = $templates->get(DocumentType::Annexes->value);

        if ($template === null) {
            return;
        }

        $document = Document::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $employee->id,
            'title' => 'Anexo de Contrato - Modificación de Remuneración',
            'type' => DocumentType::Annexes,
            'body' => $template->body,
            'status' => DocumentStatus::Signed,
            'legal_rep_signatories' => $legalRep === null ? 0 : 1,
            'published_at' => Carbon::now()->subDays(10),
            'signed_at' => Carbon::now()->subDays(8),
        ]);

        $this->freezeBody($document);

        $this->signatureFor($document, $employee, DocumentSignatureType::Employee, signed: true);

        if ($legalRep !== null) {
            $this->signatureFor($document, $legalRep, DocumentSignatureType::LegalRep, signed: true);
        }
    }

    /**
     * Create a pending or signed signature for the given signatory. Signed
     * signatures record the document's content hash as their FES evidence, so
     * the integrity check resolves against the frozen body.
     */
    private function signatureFor(Document $document, User $signatory, DocumentSignatureType $type, bool $signed = false): void
    {
        $factory = DocumentSignature::factory()->state([
            'organization_id' => $document->organization_id,
            'document_id' => $document->id,
            'user_id' => $signatory->id,
            'type' => $type,
        ]);

        if ($signed) {
            $factory->signed()->create([
                'signed_content_hash' => $document->contentHash(),
            ]);

            return;
        }

        $factory->create(['status' => DocumentSignatureStatus::Pending]);
    }

    /**
     * Resolve the document's `{{variable}}` placeholders against the employee's
     * data and persist the frozen body, mirroring what publishing does through
     * the {@see DocumentObserver} when model events are enabled.
     */
    private function freezeBody(Document $document): void
    {
        $document->update([
            'body' => app(DocumentVariableResolver::class)->resolve($document),
        ]);
    }
}
