import type { MultiComboboxOption } from '@/components/multi-combobox';

export type ReportType =
    | 'attendance'
    | 'daily'
    | 'shift-changes'
    | 'sundays'
    | 'incidents';

export type ReportOptions = {
    employees: MultiComboboxOption[];
    positions: MultiComboboxOption[];
    premises: MultiComboboxOption[];
    shifts: MultiComboboxOption[];
    journals: MultiComboboxOption[];
};

export type ReportFilters = {
    type: ReportType | null;
    start: string;
    end: string;
    employees: number[];
    positions: number[];
    premises: number[];
    journals: string[];
    shifts: number[];
    checksum: string | null;
};
