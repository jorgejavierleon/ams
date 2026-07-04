import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import {
    formatCurrency,
    formatDate,
    formatDateTime,
    formatNumber,
    formatTime,
    translate,
} from '@/lib/i18n';
import type { TranslationReplacements } from '@/types/i18n';

/**
 * Access the active locale and its translation catalog from Inertia shared
 * props. Returns a bound `t()` helper plus locale-aware Intl formatters.
 *
 * @example
 * const { t, formatDate } = useTranslations();
 * t('ui.nav.dashboard');
 * formatDate(new Date());
 */
export function useTranslations() {
    const { locale, localeTag, supportedLocales, translations } =
        usePage().props;

    return useMemo(
        () => ({
            locale,
            localeTag,
            supportedLocales,
            t: (key: string, replacements?: TranslationReplacements) =>
                translate(translations, key, replacements),
            formatDate: (
                value: Date | string | number,
                options?: Intl.DateTimeFormatOptions,
            ) => formatDate(localeTag, value, options),
            formatTime: (
                value: Date | string | number,
                options?: Intl.DateTimeFormatOptions,
            ) => formatTime(localeTag, value, options),
            formatDateTime: (
                value: Date | string | number,
                options?: Intl.DateTimeFormatOptions,
            ) => formatDateTime(localeTag, value, options),
            formatNumber: (value: number, options?: Intl.NumberFormatOptions) =>
                formatNumber(localeTag, value, options),
            formatCurrency: (
                value: number,
                currency?: string,
                options?: Intl.NumberFormatOptions,
            ) => formatCurrency(localeTag, value, currency, options),
        }),
        [locale, localeTag, supportedLocales, translations],
    );
}
