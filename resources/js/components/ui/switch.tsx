import * as React from 'react';

import { cn } from '@/lib/utils';

/**
 * A shadcn-style toggle switch built without an extra Radix dependency: a
 * `role="switch"` button driven by a controlled `checked` value. Mirrors the
 * `checked` / `onCheckedChange` API of the Radix Switch so it can be swapped
 * later without touching call sites.
 */
function Switch({
    checked = false,
    onCheckedChange,
    className,
    disabled,
    ...props
}: Omit<React.ComponentProps<'button'>, 'onChange' | 'type'> & {
    checked?: boolean;
    onCheckedChange?: (checked: boolean) => void;
}) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            data-slot="switch"
            data-state={checked ? 'checked' : 'unchecked'}
            disabled={disabled}
            onClick={() => onCheckedChange?.(!checked)}
            className={cn(
                'peer inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent shadow-xs transition-colors outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50',
                checked ? 'bg-primary' : 'bg-input',
                className,
            )}
            {...props}
        >
            <span
                className={cn(
                    'pointer-events-none block size-4 rounded-full bg-background shadow-lg ring-0 transition-transform',
                    checked ? 'translate-x-4' : 'translate-x-0',
                )}
            />
        </button>
    );
}

export { Switch };
