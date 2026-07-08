import {
    createContext,
    useContext,
    useId,
    useState,
    type ReactNode,
} from 'react';
import { cn } from '@/lib/utils';

type TabsContextValue = {
    value: string;
    setValue: (value: string) => void;
    baseId: string;
};

const TabsContext = createContext<TabsContextValue | null>(null);

function useTabsContext(): TabsContextValue {
    const context = useContext(TabsContext);

    if (!context) {
        throw new Error('Tabs components must be used within <Tabs>.');
    }

    return context;
}

type TabsProps = {
    /** Controlled active tab value. */
    value?: string;
    /** Initial active tab value for uncontrolled usage. */
    defaultValue?: string;
    onValueChange?: (value: string) => void;
    className?: string;
    children: ReactNode;
};

/**
 * Minimal, accessible tabs built on native buttons — no external dependency.
 * Mirrors the shadcn/ui Tabs API (`Tabs`, `TabsList`, `TabsTrigger`,
 * `TabsContent`) so pages read the same as the rest of the design system.
 */
export function Tabs({
    value,
    defaultValue,
    onValueChange,
    className,
    children,
}: TabsProps) {
    const [internal, setInternal] = useState(defaultValue ?? '');
    const baseId = useId();
    const current = value ?? internal;

    function setValue(next: string) {
        if (value === undefined) {
            setInternal(next);
        }

        onValueChange?.(next);
    }

    return (
        <TabsContext.Provider value={{ value: current, setValue, baseId }}>
            <div className={cn('flex flex-col gap-4', className)}>{children}</div>
        </TabsContext.Provider>
    );
}

type TabsListProps = {
    className?: string;
    children: ReactNode;
};

export function TabsList({ className, children }: TabsListProps) {
    return (
        <div
            role="tablist"
            className={cn(
                'inline-flex h-auto w-full flex-wrap items-center justify-start gap-1 rounded-lg bg-muted p-1 text-muted-foreground',
                className,
            )}
        >
            {children}
        </div>
    );
}

type TabsTriggerProps = {
    value: string;
    className?: string;
    children: ReactNode;
};

export function TabsTrigger({ value, className, children }: TabsTriggerProps) {
    const { value: active, setValue, baseId } = useTabsContext();
    const selected = active === value;

    return (
        <button
            type="button"
            role="tab"
            id={`${baseId}-trigger-${value}`}
            aria-selected={selected}
            aria-controls={`${baseId}-content-${value}`}
            data-state={selected ? 'active' : 'inactive'}
            onClick={() => setValue(value)}
            className={cn(
                'inline-flex items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium whitespace-nowrap transition-all',
                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                selected
                    ? 'bg-background text-foreground shadow-sm'
                    : 'hover:text-foreground',
                className,
            )}
        >
            {children}
        </button>
    );
}

type TabsContentProps = {
    value: string;
    className?: string;
    children: ReactNode;
};

export function TabsContent({ value, className, children }: TabsContentProps) {
    const { value: active, baseId } = useTabsContext();

    if (active !== value) {
        return null;
    }

    return (
        <div
            role="tabpanel"
            id={`${baseId}-content-${value}`}
            aria-labelledby={`${baseId}-trigger-${value}`}
            className={cn('focus-visible:outline-none', className)}
        >
            {children}
        </div>
    );
}
