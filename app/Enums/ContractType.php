<?php

namespace App\Enums;

/**
 * The kind of engagement an employee works under.
 *
 * The first three cases are the contracts the Código del Trabajo recognises for
 * dependent workers. `Honorarios` is not a contrato de trabajo at all — it is a
 * fee-for-service arrangement — but it lives here rather than behind a separate
 * boolean so that a single column stays the source of truth and cannot
 * contradict itself. Consumers that must restrict themselves to dependent
 * workers ask {@see self::isEmploymentContract()}.
 */
enum ContractType: string
{
    case Indefinido = 'indefinido';
    case PlazoFijo = 'plazo_fijo';
    case PorObraOFaena = 'por_obra_o_faena';
    case Honorarios = 'honorarios';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.employees.contract_types.'.$this->value);
    }

    /**
     * Whether this is a contrato de trabajo governed by the Código del Trabajo.
     * Honorarios workers are excluded: they have no employment relationship, so
     * attendance-derived payroll exports must not treat them as remunerated
     * dependent workers.
     */
    public function isEmploymentContract(): bool
    {
        return $this !== self::Honorarios;
    }

    /**
     * All types as value/label pairs for select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }

    /**
     * Only the contratos de trabajo, for payroll consumers that must leave
     * honorarios workers out.
     *
     * @return array<int, self>
     */
    public static function employmentContractCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $type): bool => $type->isEmploymentContract(),
        ));
    }
}
