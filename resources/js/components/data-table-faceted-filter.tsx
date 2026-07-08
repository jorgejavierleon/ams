import { Check, PlusCircle } from 'lucide-react';
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
import { Separator } from '@/components/ui/separator';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';

export type FacetedOption = { value: string; label: string };

type Props = {
    title: string;
    options: FacetedOption[];
    /** Currently selected values. */
    selected: string[];
    onChange: (values: string[]) => void;
    searchPlaceholder?: string;
    emptyLabel?: string;
};

/**
 * Multi-select filter rendered as a popover of checkable options, mirroring the
 * shadcn data-table faceted filter. Selected values surface as badges on the
 * trigger for quick scanning.
 */
export function DataTableFacetedFilter({
    title,
    options,
    selected,
    onChange,
    searchPlaceholder,
    emptyLabel,
}: Props) {
    const { t } = useTranslations();
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

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button variant="outline" className="border-dashed">
                    <PlusCircle className="size-4" />
                    {title}
                    {selectedSet.size > 0 && (
                        <>
                            <Separator
                                orientation="vertical"
                                className="mx-1 h-4"
                            />
                            <Badge
                                variant="secondary"
                                className="rounded-sm px-1 font-normal"
                            >
                                {selectedSet.size}
                            </Badge>
                        </>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-56 p-0" align="start">
                <Command>
                    <CommandInput
                        placeholder={searchPlaceholder ?? title}
                    />
                    <CommandList>
                        <CommandEmpty>
                            {emptyLabel ?? t('ui.common.data_table.empty')}
                        </CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => {
                                const isSelected = selectedSet.has(option.value);

                                return (
                                    <CommandItem
                                        key={option.value}
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
                                        <span>{option.label}</span>
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
