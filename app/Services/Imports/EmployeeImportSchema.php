<?php

namespace App\Services\Imports;

use App\Enums\ContractType;
use App\Enums\ImportFieldType;
use App\Enums\ImportStrategy;
use App\Enums\MatchKeyComparator;
use App\Http\Controllers\EmployeeController;
use App\Models\Company;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\CostCenter;
use App\Models\Position;
use App\Models\Premise;
use App\Models\User;
use App\Rules\ValidRut;
use App\Support\Imports\ImportField;
use App\Support\Imports\ReferenceResolution;
use App\Support\Rut;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * The Employee bulk-import schema (KOL-94.2): full parity with the manual
 * create/edit form's field set (the 18-column export set plus supervisor,
 * is_admin, timezone, and the day-balance fields), reference fields resolved
 * by case-insensitive exact match, and three match keys — RUT, Email, ID.
 *
 * `company` is deliberately excluded (auto-assigned per organization, per
 * KOL-32); `password` and `avatar` are excluded entirely, per KOL-94.2.
 */
final class EmployeeImportSchema implements ImportSchema
{
    /**
     * Fields required for CreateOnly, mirroring the manual form exactly
     * (KOL-94.2 #6).
     *
     * @var list<string>
     */
    private const REQUIRED_FOR_CREATE_ONLY = ['first_name', 'last_name', 'email', 'rut', 'timezone'];

    /**
     * @return array<int, ImportField>
     */
    public function fields(): array
    {
        return [
            $this->field('first_name', type: ImportFieldType::String),
            $this->field('last_name', type: ImportFieldType::String),
            $this->field('second_last_name', type: ImportFieldType::String),
            $this->field('rut', type: ImportFieldType::String, isMatchKeyEligible: true, matchKeyComparator: MatchKeyComparator::NormalizedRut),
            $this->field('email', type: ImportFieldType::String, isMatchKeyEligible: true, matchKeyComparator: MatchKeyComparator::CaseInsensitive),
            $this->field('personal_email', type: ImportFieldType::String),
            $this->field('phone', type: ImportFieldType::String),
            $this->field('nationality', type: ImportFieldType::String),
            $this->field('gender', type: ImportFieldType::String),
            $this->field('cost_center', type: ImportFieldType::String, isReference: true),
            $this->field('premise', type: ImportFieldType::String, isReference: true),
            $this->field('position', type: ImportFieldType::String, isReference: true),
            $this->field('contract_type', type: ImportFieldType::String, isReference: true),
            $this->field('contract_start_date', type: ImportFieldType::Date),
            $this->field('contract_end_date', type: ImportFieldType::Date),
            $this->field('emergency_contact_name', type: ImportFieldType::String),
            $this->field('emergency_contact_phone', type: ImportFieldType::String),
            $this->field('is_active', type: ImportFieldType::Boolean),
            $this->field('supervisor', type: ImportFieldType::String, isReference: true),
            $this->field('is_admin', type: ImportFieldType::Boolean),
            $this->field('timezone', type: ImportFieldType::String),
            $this->field('vacation_days', type: ImportFieldType::Decimal),
            $this->field('additional_vacation_days', type: ImportFieldType::Decimal),
            $this->field('administrative_days', type: ImportFieldType::Decimal),
            $this->field('has_additional_sundays', type: ImportFieldType::Boolean),
            $this->field('overtime_rest_day_eligible', type: ImportFieldType::Boolean),
            $this->field('id', type: ImportFieldType::Integer, isMatchKeyEligible: true, matchKeyComparator: MatchKeyComparator::Exact, isIdentifierOnly: true),
        ];
    }

    public function rules(ImportStrategy $strategy, ?Model $existingMatch): array
    {
        $isCreate = $existingMatch === null;
        $organizationId = Company::currentOrganizationId();
        $required = $isCreate ? 'required' : 'nullable';

        return [
            'first_name' => [$required, 'string', 'max:255'],
            'last_name' => [$required, 'string', 'max:255'],
            'second_last_name' => ['nullable', 'string', 'max:255'],
            'rut' => [$required, 'string', new ValidRut, Rule::unique('users', 'rut')->ignore($existingMatch)],
            'email' => [$required, 'email', 'max:255', Rule::unique('users', 'email')->ignore($existingMatch)],
            'personal_email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'personal_email')->ignore($existingMatch)],
            'phone' => ['nullable', 'string', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:255'],
            'cost_center_id' => ['nullable', 'integer', Rule::exists('cost_centers', 'id')->where('organization_id', $organizationId)],
            'premise_id' => ['nullable', 'integer', Rule::exists('premises', 'id')->where('organization_id', $organizationId)],
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')->where('organization_id', $organizationId)],
            'contract_type' => ['nullable', Rule::enum(ContractType::class)],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'supervisor_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('organization_id', $organizationId),
                Rule::notIn($existingMatch ? [$existingMatch->getKey()] : []),
            ],
            'is_admin' => ['nullable', 'boolean'],
            'timezone' => [$required, 'timezone'],
            'vacation_days' => ['nullable', 'numeric', 'min:0'],
            'additional_vacation_days' => ['nullable', 'numeric', 'min:0'],
            'administrative_days' => ['nullable', 'numeric', 'min:0'],
            'has_additional_sundays' => ['nullable', 'boolean'],
            'overtime_rest_day_eligible' => ['nullable', 'boolean'],
        ];
    }

    public function resolveReferences(array $row): ReferenceResolution
    {
        $resolved = $row;
        $unresolvedFields = [];

        if (array_key_exists('rut', $row) && $row['rut'] !== null) {
            $resolved['rut'] = Rut::normalize((string) $row['rut']);
        }

        foreach ($this->referenceLookups() as $field => $lookup) {
            if (! array_key_exists($field, $row) || $row[$field] === null) {
                continue;
            }

            unset($resolved[$field]);

            $id = $lookup((string) $row[$field]);

            if ($id === null) {
                $unresolvedFields[] = $field;

                continue;
            }

            $resolved[$field.'_id'] = $id;
        }

        if (array_key_exists('contract_type', $row) && $row['contract_type'] !== null) {
            $label = Str::lower(trim((string) $row['contract_type']));

            $contractType = collect(ContractType::cases())
                ->first(fn (ContractType $type): bool => Str::lower($type->label()) === $label);

            if ($contractType === null) {
                $unresolvedFields[] = 'contract_type';
            } else {
                $resolved['contract_type'] = $contractType->value;
            }
        }

        return new ReferenceResolution($resolved, $unresolvedFields);
    }

    public function findExisting(string $matchKey, mixed $normalizedValue): ?Model
    {
        $organizationId = Company::currentOrganizationId();

        return match ($matchKey) {
            'rut' => User::query()->where('organization_id', $organizationId)->where('rut', $normalizedValue)->first(),
            'email' => User::query()->where('organization_id', $organizationId)->whereRaw('LOWER(email) = ?', [$normalizedValue])->first(),
            'id' => User::query()->where('organization_id', $organizationId)->whereKey($normalizedValue)->first(),
            default => throw new InvalidArgumentException("Unsupported Employee match key: {$matchKey}"),
        };
    }

    public function targetModel(): string
    {
        return User::class;
    }

    /**
     * cost_center/premise/position are scoped by {@see BelongsToOrganization}'s
     * global scope already; User (the supervisor lookup) carries no such
     * scope, so it is filtered explicitly, matching {@see EmployeeController::validateEmployee()}.
     *
     * @return array<string, callable(string): ?int>
     */
    private function referenceLookups(): array
    {
        return [
            'cost_center' => fn (string $label): ?int => CostCenter::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower(trim($label))])->value('id'),
            'premise' => fn (string $label): ?int => Premise::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower(trim($label))])->value('id'),
            'position' => fn (string $label): ?int => Position::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower(trim($label))])->value('id'),
            'supervisor' => fn (string $label): ?int => User::query()
                ->where('organization_id', Company::currentOrganizationId())
                ->where('rut', Rut::normalize(trim($label)))->value('id'),
        ];
    }

    private function field(
        string $name,
        ImportFieldType $type,
        bool $isReference = false,
        bool $isMatchKeyEligible = false,
        ?MatchKeyComparator $matchKeyComparator = null,
        bool $isIdentifierOnly = false,
    ): ImportField {
        return new ImportField(
            name: $name,
            label: __('ui.employees.form.'.$name),
            type: $type,
            requiredForCreateOnly: in_array($name, self::REQUIRED_FOR_CREATE_ONLY, true),
            isReference: $isReference,
            isMatchKeyEligible: $isMatchKeyEligible,
            matchKeyComparator: $matchKeyComparator,
            isIdentifierOnly: $isIdentifierOnly,
        );
    }
}
