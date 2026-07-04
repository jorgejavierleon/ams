import { router } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import {
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu';
import { useTranslations } from '@/hooks/use-translations';
import { update as updateLocale } from '@/routes/locale';

type Props = {
    /** Called before navigating (e.g. to close a mobile nav). */
    onNavigate?: () => void;
};

/**
 * Switches the active UI locale. Persists the choice server-side via the
 * locale route; Inertia reloads shared props so the whole UI re-renders in the
 * new language on the next visit.
 */
export function LanguageSwitcher({ onNavigate }: Props) {
    const { t, locale, supportedLocales } = useTranslations();

    const handleChange = (next: string) => {
        if (next === locale) {
            return;
        }

        onNavigate?.();
        router.put(updateLocale(next).url, {}, { preserveScroll: true });
    };

    return (
        <DropdownMenuSub>
            <DropdownMenuSubTrigger>
                <Languages className="mr-2" />
                {t('ui.language.label')}
            </DropdownMenuSubTrigger>
            <DropdownMenuSubContent>
                <DropdownMenuRadioGroup
                    value={locale}
                    onValueChange={handleChange}
                >
                    {supportedLocales.map((code) => (
                        <DropdownMenuRadioItem key={code} value={code}>
                            {t(`ui.language.${code}`)}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuSubContent>
        </DropdownMenuSub>
    );
}
