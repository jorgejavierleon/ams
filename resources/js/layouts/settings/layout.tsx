import { Link, usePage } from '@inertiajs/react';
import {
    Bell,
    Crown,
    FileText,
    Palette,
    ShieldCheck,
    Timer,
    User,
} from 'lucide-react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useTranslations } from '@/hooks/use-translations';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { edit as editDocuments } from '@/routes/settings-documents';
import { edit as editNotifications } from '@/routes/settings-notifications';
import { edit as editOrganization } from '@/routes/settings-organization';
import { edit as editOvertime } from '@/routes/settings-overtime';
import type { NavItem } from '@/types';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { t } = useTranslations();
    const { auth } = usePage().props;

    // Same permission check app-sidebar.tsx uses to split employee vs admin
    // nav groups: the organization-wide sections below are admin-only, same
    // as the role:admin gate their backend routes carry.
    const isEmployee = auth.permissions.includes('ViewOwn:Leave');

    const sidebarNavItems: NavItem[] = [
        {
            title: t('ui.settings.nav.profile'),
            href: editProfile(),
            icon: User,
        },
        {
            title: t('ui.settings.nav.security'),
            href: editSecurity(),
            icon: ShieldCheck,
        },
        {
            title: t('ui.settings.nav.appearance'),
            href: editAppearance(),
            icon: Palette,
        },
        ...(!isEmployee
            ? [
                  {
                      title: t('ui.settings.nav.notifications'),
                      href: editNotifications(),
                      icon: Bell,
                  },
                  {
                      title: t('ui.settings.nav.documents'),
                      href: editDocuments(),
                      icon: FileText,
                  },
                  {
                      title: t('ui.settings.nav.overtime'),
                      href: editOvertime(),
                      icon: Timer,
                  },
              ]
            : []),
        // The Owner must always be able to reach ownership transfer, even
        // when they hold no admin role (KOL-133.2), so this doesn't gate on
        // isEmployee the way the admin-only sections above do.
        ...(!isEmployee || auth.isOwner
            ? [
                  {
                      title: t('ui.settings.nav.organization'),
                      href: editOrganization(),
                      icon: Crown,
                  },
              ]
            : []),
    ];

    return (
        <div className="px-6 py-10">
            <Heading
                title={t('ui.settings.title')}
                description={t('ui.settings.description')}
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-80">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Settings"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant={
                                    isCurrentOrParentUrl(item.href)
                                        ? 'default'
                                        : 'ghost'
                                }
                                asChild
                                className="w-full justify-start"
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1">
                    <section className="space-y-12">{children}</section>
                </div>
            </div>
        </div>
    );
}
