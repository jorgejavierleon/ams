import { Check, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import type { ReportFacetOption } from './types';

type Props = {
    title: string;
    options: ReportFacetOption[];
    selected: string[];
    onChange: (values: string[]) => void;
};

/**
 * The report-picker's facet filter (KOL-89): a richer variant of
 * `DataTableFacetedFilter` scoped to the payroll-reports employee picker —
 * per-option counts, a per-facet "Limpiar" action and an explicit "Listo"
 * button to close it. Kept as its own component rather than changing the
 * shared `DataTableFacetedFilter`, which the employees/leaves/workdays tables
 * also render and must keep looking the way it does today.
 */
export function ReportFacetFilter({ title, options, selected, onChange }: Props) {
    const { t } = useTranslations();
    const [open, setOpen] = useState(false);
    const selectedSet = new Set(selected);

    function toggle(value: string) {
        const next = new Set(selectedSet);

        if (next.has(value)) {
            next.delete(value);
        } else {
            next.add(value);
        }

        onChange([...next]);
    }

    const buttonLabel =
        selectedSet.size === 1
            ? `${title}: ${options.find((option) => option.value === selected[0])?.label ?? selected[0]}`
            : title;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className={cn(
                        'inline-flex h-8 items-center gap-1.5 rounded-full border bg-card px-3 text-sm font-medium hover:border-muted-foreground',
                        selectedSet.size > 0 && 'border-muted-foreground bg-muted',
                    )}
                >
                    {buttonLabel}
                    {selectedSet.size > 1 && (
                        <Badge
                            variant="secondary"
                            className="rounded-full px-1.5 font-normal"
                        >
                            {selectedSet.size}
                        </Badge>
                    )}
                    <ChevronDown className="size-3.5 text-muted-foreground" />
                </button>
            </PopoverTrigger>
            <PopoverContent className="w-72 p-0" align="start">
                <Command>
                    <CommandInput placeholder={title} />
                    <div className="flex items-center justify-between border-b px-3 py-1.5">
                        <span className="text-xs text-muted-foreground">
                            {selectedSet.size > 0
                                ? t('ui.payroll_reports.filters.facet.selected_summary', {
                                      count: String(selectedSet.size),
                                  })
                                : t('ui.payroll_reports.filters.facet.no_selection')}
                        </span>
                        {selectedSet.size > 0 && (
                            <button
                                type="button"
                                onClick={() => onChange([])}
                                className="text-xs text-primary underline underline-offset-2"
                            >
                                {t('ui.payroll_reports.filters.facet.clear')}
                            </button>
                        )}
                    </div>
                    <CommandList>
                        <CommandEmpty>
                            {t('ui.common.data_table.empty')}
                        </CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => {
                                const isSelected = selectedSet.has(option.value);

                                return (
                                    <CommandItem
                                        key={option.value}
                                        value={option.label}
                                        onSelect={() => toggle(option.value)}
                                    >
                                        <div
                                            className={cn(
                                                'flex size-4 items-center justify-center rounded-sm border border-primary',
                                                isSelected
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'opacity-50 [&_svg]:invisible',
                                            )}
                                        >
                                            <Check className="size-3.5" />
                                        </div>
                                        <span className="flex-1">{option.label}</span>
                                        <span className="text-xs text-muted-foreground">
                                            {option.count}
                                        </span>
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                    </CommandList>
                    <div className="flex justify-end border-t p-2">
                        <Button
                            type="button"
                            size="sm"
                            onClick={() => setOpen(false)}
                        >
                            {t('ui.payroll_reports.filters.facet.done')}
                        </Button>
                    </div>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
