/**
 * Shared semantic status palette. The backend resolves a status into a tone
 * (`success` / `warning` / `destructive` / `neutral`) once — via each enum's
 * `badge()` — and the UI turns that tone into a soft tinted chip or a solid
 * timeline dot here, so every view stays visually coherent.
 */

const TONE_CHIP: Record<string, string> = {
    success:
        'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-900/60',
    warning:
        'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-900/60',
    destructive:
        'bg-red-50 text-red-700 border-red-200 dark:bg-red-950/40 dark:text-red-400 dark:border-red-900/60',
};

const TONE_DOT: Record<string, string> = {
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    destructive: 'bg-red-500',
};

/** Tailwind classes for a soft, tinted status chip keyed by tone. */
export function toneChip(tone: string | null | undefined): string {
    return (
        TONE_CHIP[tone ?? ''] ?? 'bg-muted text-muted-foreground border-border'
    );
}

/** Tailwind background for a solid status dot keyed by tone. */
export function toneDot(tone: string | null | undefined): string {
    return TONE_DOT[tone ?? ''] ?? 'bg-muted-foreground';
}
