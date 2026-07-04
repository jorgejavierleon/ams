import type { TranslationReplacements, Translations } from '@/types/i18n';

/**
 * Resolve a dot-notation key (e.g. `ui.nav.dashboard`) against the shared
 * translation payload, substituting any `:token` placeholders.
 *
 * Missing keys return the key itself so that an untranslated string is visible
 * in the UI (and easy to spot) rather than rendering blank.
 */
export function translate(
    translations: Translations,
    key: string,
    replacements: TranslationReplacements = {},
): string {
    const value = key
        .split('.')
        .reduce<string | Translations | undefined>((carry, segment) => {
            if (carry && typeof carry === 'object' && segment in carry) {
                return carry[segment];
            }

            return undefined;
        }, translations);

    if (typeof value !== 'string') {
        return key;
    }

    // Replace longer tokens first so a token that is a prefix of another
    // (e.g. `:to` within `:total`) does not corrupt the longer placeholder.
    return Object.entries(replacements)
        .sort(([a], [b]) => b.length - a.length)
        .reduce(
            (message, [token, replacement]) =>
                message.replaceAll(`:${token}`, String(replacement)),
            value,
        );
}

/**
 * Locale-aware formatters. `localeTag` is the BCP 47 tag shared from the
 * backend (e.g. `es-CL`), so date, time and number output follow the active
 * locale's conventions (Chile: `dd/mm/yyyy`, 24h clock, `.`/`,` separators).
 */
export function formatDate(
    localeTag: string,
    value: Date | string | number,
    options: Intl.DateTimeFormatOptions = { dateStyle: 'short' },
): string {
    return new Intl.DateTimeFormat(localeTag, options).format(toDate(value));
}

export function formatTime(
    localeTag: string,
    value: Date | string | number,
    options: Intl.DateTimeFormatOptions = {
        timeStyle: 'short',
        hourCycle: 'h23',
    },
): string {
    return new Intl.DateTimeFormat(localeTag, options).format(toDate(value));
}

export function formatDateTime(
    localeTag: string,
    value: Date | string | number,
    options: Intl.DateTimeFormatOptions = {
        dateStyle: 'short',
        timeStyle: 'short',
        hourCycle: 'h23',
    },
): string {
    return new Intl.DateTimeFormat(localeTag, options).format(toDate(value));
}

export function formatNumber(
    localeTag: string,
    value: number,
    options: Intl.NumberFormatOptions = {},
): string {
    return new Intl.NumberFormat(localeTag, options).format(value);
}

export function formatCurrency(
    localeTag: string,
    value: number,
    currency = 'CLP',
    options: Intl.NumberFormatOptions = {},
): string {
    return new Intl.NumberFormat(localeTag, {
        style: 'currency',
        currency,
        ...options,
    }).format(value);
}

function toDate(value: Date | string | number): Date {
    return value instanceof Date ? value : new Date(value);
}
