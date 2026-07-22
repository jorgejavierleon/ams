import { Check, ChevronsUpDown, X } from 'lucide-react';
import { useMemo, useState } from 'react';
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
import { cn } from '@/lib/utils';

/** Lower-case and strip diacritics so search is case- and accent-insensitive. */
const fold = (text: string): string =>
    text
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();

/**
 * Contiguous substring match across an item's label and keywords. Replaces
 * cmdk's default fuzzy scorer, which matches scattered subsequences — so a
 * typed RUT like `229018213` would wrongly surface an unrelated worker whose
 * digits merely contain that subsequence.
 */
const substringFilter = (
    value: string,
    search: string,
    keywords?: string[],
): number => {
    const haystack = fold([value, ...(keywords ?? [])].join(' '));

    return haystack.includes(fold(search)) ? 1 : 0;
};

export type MultiComboboxOption = {
    value: string;
    label: string;
    /**
     * Extra text the search should match beyond the visible label — e.g. a
     * worker's RUT in forms the label doesn't display. Fed to cmdk's per-item
     * `keywords` so typing any of them surfaces the option.
     */
    keywords?: string[];
};

type Props = {
    options: MultiComboboxOption[];
    /** Selected option values. */
    value: string[];
    onChange: (value: string[]) => void;
    id?: string;
    placeholder: string;
    searchPlaceholder: string;
    emptyLabel: string;
    /** Label for the "N selected" summary; receives the count. */
    summaryLabel: (count: number) => string;
    disabled?: boolean;
    /**
     * Render the popover in modal mode. Required when the combobox lives inside
     * a Radix Dialog: a non-modal popover nested in a modal dialog loses
     * pointer handling, so clicking an option closes the list without selecting.
     */
    modal?: boolean;
};

/**
 * A multi-select combobox with type-to-filter search, built on Popover + cmdk.
 * Toggling an option keeps the list open so several values can be picked at
 * once; selected options surface as removable badges below the trigger. Values
 * are strings so it drops straight into Inertia `useForm` array fields.
 */
export function MultiCombobox({
    options,
    value,
    onChange,
    id,
    placeholder,
    searchPlaceholder,
    emptyLabel,
    summaryLabel,
    disabled,
    modal,
}: Props) {
    const [open, setOpen] = useState(false);

    const selectedOptions = useMemo(
        () => options.filter((option) => value.includes(option.value)),
        [options, value],
    );

    const toggle = (optionValue: string) => {
        onChange(
            value.includes(optionValue)
                ? value.filter((current) => current !== optionValue)
                : [...value, optionValue],
        );
    };

    return (
        <div className="grid gap-2">
            <Popover open={open} onOpenChange={setOpen} modal={modal}>
                <PopoverTrigger asChild>
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        disabled={disabled}
                        className="w-full justify-between font-normal"
                    >
                        <span
                            className={cn(
                                'truncate',
                                value.length === 0 && 'text-muted-foreground',
                            )}
                        >
                            {value.length === 0
                                ? placeholder
                                : summaryLabel(value.length)}
                        </span>
                        <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    className="w-(--radix-popover-trigger-width) p-0"
                    align="start"
                >
                    <Command filter={substringFilter}>
                        <CommandInput placeholder={searchPlaceholder} />
                        <CommandList>
                            <CommandEmpty>{emptyLabel}</CommandEmpty>
                            <CommandGroup>
                                {options.map((option) => (
                                    <CommandItem
                                        key={option.value}
                                        value={option.label}
                                        keywords={option.keywords}
                                        onSelect={() => toggle(option.value)}
                                    >
                                        <Check
                                            className={cn(
                                                'size-4',
                                                value.includes(option.value)
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                        {option.label}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>

            {selectedOptions.length > 0 && (
                <div className="flex flex-wrap gap-1">
                    {selectedOptions.map((option) => (
                        <Badge
                            key={option.value}
                            variant="secondary"
                            className="gap-1 pr-1"
                        >
                            {option.label}
                            <button
                                type="button"
                                onClick={() => toggle(option.value)}
                                disabled={disabled}
                                className="rounded-sm opacity-60 transition-opacity hover:opacity-100"
                                aria-label={option.label}
                            >
                                <X className="size-3" />
                            </button>
                        </Badge>
                    ))}
                </div>
            )}
        </div>
    );
}
