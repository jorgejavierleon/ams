import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslations } from '@/hooks/use-translations';
import { destroy } from '@/routes/totem-devices';

type TotemDevice = {
    id: number;
    name: string;
    premise: string | null;
    created_at: string;
    last_used_at: string | null;
};

type Props = {
    devices: TotemDevice[];
};

export default function TotemDevicesIndex({ devices }: Props) {
    const { t, formatDateTime } = useTranslations();
    const [revokeTarget, setRevokeTarget] = useState<TotemDevice | null>(null);

    function confirmRevoke() {
        if (!revokeTarget) {
            return;
        }

        router.delete(destroy(revokeTarget.id).url, {
            preserveScroll: true,
            onFinish: () => setRevokeTarget(null),
        });
    }

    return (
        <>
            <Head title={t('ui.totem_devices.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.totem_devices.title')}
                    description={t('ui.totem_devices.description')}
                />

                {devices.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        {t('ui.totem_devices.empty')}
                    </p>
                ) : (
                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('ui.totem_devices.columns.name')}
                                    </TableHead>
                                    <TableHead>
                                        {t('ui.totem_devices.columns.premise')}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.totem_devices.columns.created_at',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.totem_devices.columns.last_used_at',
                                        )}
                                    </TableHead>
                                    <TableHead className="w-0" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {devices.map((device) => (
                                    <TableRow key={device.id}>
                                        <TableCell className="font-medium">
                                            {device.name}
                                        </TableCell>
                                        <TableCell>
                                            {device.premise ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {formatDateTime(device.created_at)}
                                        </TableCell>
                                        <TableCell>
                                            {device.last_used_at
                                                ? formatDateTime(
                                                      device.last_used_at,
                                                  )
                                                : t(
                                                      'ui.totem_devices.never_used',
                                                  )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() =>
                                                        setRevokeTarget(device)
                                                    }
                                                >
                                                    {t(
                                                        'ui.totem_devices.actions.revoke',
                                                    )}
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={revokeTarget !== null}
                onOpenChange={(open) => !open && setRevokeTarget(null)}
                title={t('ui.totem_devices.revoke_dialog.title')}
                description={t('ui.totem_devices.revoke_dialog.description', {
                    name: revokeTarget?.name ?? '',
                })}
                confirmLabel={t('ui.totem_devices.revoke_dialog.confirm')}
                onConfirm={confirmRevoke}
            />
        </>
    );
}
