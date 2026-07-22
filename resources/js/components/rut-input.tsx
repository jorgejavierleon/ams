import type { ComponentProps, Ref } from 'react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { formatRut, validateRut } from '@/lib/rut';

type Props = Omit<ComponentProps<'input'>, 'value' | 'onChange'> & {
    value: string;
    onChange: (value: string) => void;
    ref?: Ref<HTMLInputElement>;
};

/**
 * Formats a Chilean RUT to `XX.XXX.XXX-X` as the user types. The invalid
 * state only appears after the field is blurred, so the modulo-11 check
 * doesn't flash red while the verifier digit is still being entered — the
 * backend's `ValidRut` rule remains the authority that blocks submission.
 */
export default function RutInput({
    value,
    onChange,
    onBlur,
    ref,
    ...props
}: Props) {
    const [touched, setTouched] = useState(false);
    const isInvalid = touched && value.length > 0 && !validateRut(value);

    return (
        <Input
            placeholder="12.345.678-9"
            {...props}
            ref={ref}
            value={value}
            onChange={(e) => onChange(formatRut(e.target.value))}
            onBlur={(e) => {
                setTouched(true);
                onBlur?.(e);
            }}
            aria-invalid={isInvalid || undefined}
        />
    );
}
