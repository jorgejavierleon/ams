<?php

namespace App\Services\Documents;

use App\Models\Document;
use App\Models\User;
use Database\Seeders\DocumentVarSeeder;

/**
 * Resolves the `{{variable}}` placeholders in a document body against the
 * concrete data of the document's employee and the records around them
 * (company, workplace, position, organization and legal representative).
 *
 * Placeholder keys mirror the tokens seeded by {@see DocumentVarSeeder}
 * and are stored with their surrounding braces. Any token without a mapping is
 * left untouched so an unknown placeholder degrades to visible text rather than
 * silently disappearing.
 */
class DocumentVariableResolver
{
    /**
     * Replace every known placeholder in the document body with its value.
     */
    public function resolve(Document $document): string
    {
        $body = (string) $document->body;

        if ($body === '') {
            return $body;
        }

        $replacements = $this->replacementsFor($document);

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $body,
        );
    }

    /**
     * Build the token => value map for a document.
     *
     * @return array<string, string>
     */
    private function replacementsFor(Document $document): array
    {
        $employee = $document->user;
        $employee->loadMissing(['company', 'premise', 'position']);

        $company = $employee->company;
        $premise = $employee->premise;
        $legalRep = $this->legalRepresentative($document);

        return array_map(
            fn (mixed $value): string => $this->stringify($value),
            [
                // --- Employee ---
                '{{employee_name}}' => $employee->name,
                '{{employee_first_name}}' => $employee->first_name,
                '{{employee_last_name}}' => $employee->last_name,
                '{{employee_second_last_name}}' => $employee->second_last_name,
                '{{employee_rut}}' => $employee->formatted_rut,
                '{{employee_nationality}}' => $employee->nationality,
                '{{employee_email}}' => $employee->email,
                '{{employee_personal_email}}' => $employee->personal_email,
                '{{employee_phone}}' => $employee->phone,
                '{{employee_position}}' => $employee->position?->name,
                '{{emergency_contact_name}}' => $employee->emergency_contact_name,
                '{{emergency_contact_phone}}' => $employee->emergency_contact_phone,

                // --- Contract ---
                '{{contract_start_date}}' => $employee->contract_start_date?->format('d-m-Y'),
                '{{contract_end_date}}' => $employee->contract_end_date?->format('d-m-Y'),
                '{{vacation_days}}' => $employee->vacation_days,

                // --- Employer company ---
                '{{company_social_reason}}' => $company?->social_reason,
                '{{company_rut}}' => $company?->rut,
                '{{company_business_line}}' => $company?->business_line,
                '{{company_address}}' => $company?->address,
                '{{company_email}}' => $company?->email,
                '{{company_phone}}' => $company?->phone,

                // --- Legal representative ---
                '{{legal_rep_name}}' => $legalRep?->name,
                '{{legal_rep_rut}}' => $legalRep?->formatted_rut,

                // --- Workplace ---
                '{{premise_name}}' => $premise?->name,
                '{{premise_address}}' => $premise?->address,
                '{{premise_commune}}' => $premise?->commune,
                '{{premise_region}}' => $premise?->region,

                // --- Organization & document ---
                '{{organization_name}}' => $document->organization?->name,
                '{{document_date}}' => now()->format('d-m-Y'),
            ],
        );
    }

    /**
     * The organization's legal representative, if one is recorded.
     */
    private function legalRepresentative(Document $document): ?User
    {
        return User::query()
            ->where('organization_id', $document->organization_id)
            ->where('is_legal_rep', true)
            ->first();
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
        }

        return (string) $value;
    }
}
