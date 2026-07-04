import type { Auth } from '@/types/auth';
import type { Translations } from '@/types/i18n';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            locale: string;
            localeTag: string;
            supportedLocales: string[];
            translations: Translations;
            auth: Auth;
            flash: {
                success: string | null;
                error: string | null;
            };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
