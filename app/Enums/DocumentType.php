<?php

namespace App\Enums;

enum DocumentType: string
{
    case Annexes = 'annexes';
    case Contracts = 'contracts';
    case Certificates = 'certificates';
    case Regulations = 'regulations';
    case Pacts = 'pacts';
    case Notifications = 'notifications';
    case Requests = 'requests';
    case Others = 'others';

    /**
     * Human-readable, translated label for display in the UI.
     */
    public function label(): string
    {
        return __('ui.documents.types.'.$this->value);
    }

    /**
     * Whether documents of this type expose the signature configuration.
     *
     * Only agreements that are actually signed by the parties (contracts,
     * annexes and pacts) offer signatory options; the remaining types are
     * informational and hide that section on the form.
     */
    public function requiresSignatureConfig(): bool
    {
        return match ($this) {
            self::Contracts, self::Annexes, self::Pacts => true,
            default => false,
        };
    }

    /**
     * All types as select options, carrying the `signable` flag the form uses
     * to decide whether to reveal the signature configuration.
     *
     * @return array<int, array{value: string, label: string, signable: bool}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'signable' => $type->requiresSignatureConfig(),
            ],
            self::cases(),
        );
    }
}
