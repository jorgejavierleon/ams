/**
 * Nested translation catalog shared from the server (Laravel lang files).
 * Keys are namespaces (e.g. `ui`) resolving to nested string maps.
 */
export type Translations = {
    [key: string]: string | Translations;
};

/** Values accepted as `:token` replacements in a translation string. */
export type TranslationReplacements = Record<string, string | number>;
