import { Bell, CheckCircle2, XCircle } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useTranslations } from '@/hooks/use-translations';
import { csrfHeader } from '@/lib/csrf';
import { cn } from '@/lib/utils';
import { index as notificationsIndex, read as markRead } from '@/routes/notifications';

/**
 * Polling interval for the bell (KOL-87 AC #2). Report exports are a
 * low-frequency event, so this stays a plain JSON poll rather than pulling
 * in a broadcasting driver (Echo + Reverb/Pusher) — see the task notes.
 */
const POLL_INTERVAL_MS = 20_000;

type NotificationData =
    | { kind: 'report_export_ready'; report_export_id: number; url: string }
    | { kind: 'report_export_failed'; report_export_id: number };

type AppNotification = {
    id: string;
    data: NotificationData;
    readAt: string | null;
    createdAt: string | null;
};

type NotificationsResponse = {
    notifications: AppNotification[];
    unreadCount: number;
};

async function fetchNotifications(): Promise<NotificationsResponse | null> {
    const response = await fetch(notificationsIndex().url, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        return null;
    }

    return (await response.json()) as NotificationsResponse;
}

function NotificationRow({
    notification,
    onRead,
}: {
    notification: AppNotification;
    onRead: (id: string) => void;
}) {
    const { t, formatDateTime } = useTranslations();
    const unread = notification.readAt === null;

    const rowClassName = cn(
        'flex items-start gap-3 rounded-md px-2 py-2 text-left text-sm',
        unread ? 'bg-accent/50' : undefined,
    );

    const content =
        notification.data.kind === 'report_export_ready' ? (
            <>
                <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                <div className="min-w-0 flex-1">
                    <p className="font-medium">
                        {t('ui.notifications.report_export_ready.title')}
                    </p>
                    <p className="text-muted-foreground">
                        {t(
                            'ui.notifications.report_export_ready.description',
                        )}
                    </p>
                </div>
            </>
        ) : (
            <>
                <XCircle className="mt-0.5 size-4 shrink-0 text-destructive" />
                <div className="min-w-0 flex-1">
                    <p className="font-medium">
                        {t('ui.notifications.report_export_failed.title')}
                    </p>
                    <p className="text-muted-foreground">
                        {t(
                            'ui.notifications.report_export_failed.description',
                        )}
                    </p>
                </div>
            </>
        );

    const timestamp = notification.createdAt && (
        <p className="mt-1 text-xs text-muted-foreground">
            {formatDateTime(notification.createdAt)}
        </p>
    );

    if (notification.data.kind === 'report_export_ready') {
        return (
            <a
                href={notification.data.url}
                onClick={() => onRead(notification.id)}
                className={cn(rowClassName, 'hover:bg-accent block')}
            >
                {content}
                {timestamp}
            </a>
        );
    }

    return (
        <button
            type="button"
            onClick={() => onRead(notification.id)}
            className={cn(rowClassName, 'hover:bg-accent w-full')}
        >
            {content}
            {timestamp}
        </button>
    );
}

/**
 * The in-app notification bell (KOL-87): polls for report-export outcomes
 * so the requester sees them without reloading, wherever they're currently
 * working. Mounted in both {@see AppSidebarHeader} (the main app, including
 * payroll reports) and {@see DtLayout} (the DT panel, today's only trigger
 * for a queued export) — both guards share the same User/notifiable model.
 */
export function NotificationBell() {
    const { t } = useTranslations();
    const [notifications, setNotifications] = useState<AppNotification[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);

    const refresh = useCallback(() => {
        void fetchNotifications().then((result) => {
            if (result === null) {
                return;
            }

            setNotifications(result.notifications);
            setUnreadCount(result.unreadCount);
        });
    }, []);

    useEffect(() => {
        refresh();

        const interval = setInterval(refresh, POLL_INTERVAL_MS);

        return () => clearInterval(interval);
    }, [refresh]);

    function handleRead(id: string) {
        setNotifications((current) =>
            current.map((notification) =>
                notification.id === id
                    ? { ...notification, readAt: new Date().toISOString() }
                    : notification,
            ),
        );
        setUnreadCount((count) => Math.max(0, count - 1));

        void fetch(markRead(id).url, {
            method: 'POST',
            headers: { Accept: 'application/json', ...csrfHeader() },
        });
    }

    return (
        <Popover onOpenChange={(open) => open && refresh()}>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={t('ui.notifications.open')}
                >
                    <Bell className="size-5" />
                    {unreadCount > 0 && (
                        <Badge
                            variant="destructive"
                            className="absolute -top-1 -right-1 h-5 min-w-5 justify-center rounded-full px-1 text-[10px]"
                        >
                            {unreadCount > 9 ? '9+' : unreadCount}
                        </Badge>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-80 p-2">
                <p className="px-2 py-1 text-sm font-semibold">
                    {t('ui.notifications.title')}
                </p>
                {notifications.length === 0 ? (
                    <p className="px-2 py-4 text-center text-sm text-muted-foreground">
                        {t('ui.notifications.empty')}
                    </p>
                ) : (
                    <div className="max-h-80 space-y-1 overflow-y-auto">
                        {notifications.map((notification) => (
                            <NotificationRow
                                key={notification.id}
                                notification={notification}
                                onRead={handleRead}
                            />
                        ))}
                    </div>
                )}
            </PopoverContent>
        </Popover>
    );
}
