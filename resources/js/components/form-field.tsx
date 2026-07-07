import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Props = {
    /** Field label text. */
    label: string;
    /** `id` of the control this label points at. */
    htmlFor?: string;
    /** Renders a red required marker after the label. */
    required?: boolean;
    /** Server-side validation message for this field, if any. */
    error?: string;
    /** Optional helper text, hidden while an error is shown. */
    hint?: string;
    className?: string;
    children: ReactNode;
};

/**
 * Standard labelled form field: a label (with an optional required marker),
 * the control, an optional hint, and the server-side validation error.
 *
 * Validation is driven by the backend — forms should set `noValidate` and rely
 * on the `error` messages surfaced here rather than native browser validation.
 */
export function FormField({
    label,
    htmlFor,
    required,
    error,
    hint,
    className,
    children,
}: Props) {
    return (
        <div className={cn('grid gap-2', className)}>
            <Label htmlFor={htmlFor}>
                {label}
                {required && (
                    <span
                        aria-hidden="true"
                        className="ml-0.5 text-red-600 dark:text-red-400"
                    >
                        *
                    </span>
                )}
            </Label>
            {children}
            {hint && !error && (
                <p className="text-xs text-muted-foreground">{hint}</p>
            )}
            <InputError message={error} />
        </div>
    );
}
