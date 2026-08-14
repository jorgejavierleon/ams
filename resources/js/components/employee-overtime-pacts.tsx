import { router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import OvertimePactFormDialog from '@/components/overtime-pact-form-dialog';
import type { OvertimePactFormTarget } from '@/components/overtime-pact-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslations } from '@/hooks/use-translations';
import { activate, revoke } from '@/routes/overtime/pacts';

export type EmployeeOvertimePact = {
    id: number;
    user_id: number;
    start_date: string;
    end_date: string;
    status: {
        value: string;
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
    };
};

type Props = {
    employeeId: number;
    employeeName: string;
    pacts: EmployeeOvertimePact[];
    canManage: boolean;
};

export function EmployeeOvertimePacts({
    employeeId,
    employeeName,
    pacts,
    canManage,
}: Props) {
    const { t } = useTranslations();

    const [formOpen, setFormOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<OvertimePactFormTarget>(null);
    const [revokeTarget, setRevokeTarget] =
        useState<EmployeeOvertimePact | null>(null);
    const [processingRow, setProcessingRow] = useState(false);

    function openCreate() {
        setEditTarget(null);
        setFormOpen(true);
    }

    function openEdit(pact: EmployeeOvertimePact) {
        setEditTarget({
            id: pact.id,
            user_id: pact.user_id,
            start_date: pact.start_date,
            end_date: pact.end_date,
        });
        setFormOpen(true);
    }

    function confirmRevoke() {
        if (!revokeTarget) {
            return;
        }

        router.patch(revoke(revokeTarget.id).url, undefined, {
            preserveScroll: true,
            onStart: () => setProcessingRow(true),
            onFinish: () => setProcessingRow(false),
            onSuccess: () => setRevokeTarget(null),
        });
    }

    function reactivate(pact: EmployeeOvertimePact) {
        router.patch(activate(pact.id).url, undefined, {
            preserveScroll: true,
        });
    }

    return (
        <Card>
            <CardContent className="space-y-4 pt-6">
                <div className="flex items-center justify-between gap-4">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        {t('ui.overtime.pacts.title')}
                    </h2>
                    {canManage && (
                        <Button size="sm" onClick={openCreate}>
                            <Plus className="size-4" />
                            {t('ui.overtime.pacts.new')}
                        </Button>
                    )}
                </div>

                {pacts.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        {t('ui.overtime.pacts.empty')}
                    </p>
                ) : (
                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t(
                                            'ui.overtime.pacts.columns.start_date',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.overtime.pacts.columns.end_date',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.overtime.pacts.columns.status',
                                        )}
                                    </TableHead>
                                    {canManage && (
                                        <TableHead className="w-0" />
                                    )}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {pacts.map((pact) => (
                                    <TableRow key={pact.id}>
                                        <TableCell>
                                            {pact.start_date}
                                        </TableCell>
                                        <TableCell>{pact.end_date}</TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={pact.status.variant}
                                            >
                                                {pact.status.label}
                                            </Badge>
                                        </TableCell>
                                        {canManage && (
                                            <TableCell>
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            openEdit(pact)
                                                        }
                                                    >
                                                        {t(
                                                            'ui.overtime.pacts.actions.edit',
                                                        )}
                                                    </Button>
                                                    {pact.status.value ===
                                                        'active' && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="text-destructive hover:text-destructive"
                                                            onClick={() =>
                                                                setRevokeTarget(
                                                                    pact,
                                                                )
                                                            }
                                                        >
                                                            {t(
                                                                'ui.overtime.pacts.actions.revoke',
                                                            )}
                                                        </Button>
                                                    )}
                                                    {pact.status.value ===
                                                        'revoked' && (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() =>
                                                                reactivate(
                                                                    pact,
                                                                )
                                                            }
                                                        >
                                                            {t(
                                                                'ui.overtime.pacts.actions.activate',
                                                            )}
                                                        </Button>
                                                    )}
                                                </div>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </CardContent>

            {canManage && (
                <>
                    <OvertimePactFormDialog
                        open={formOpen}
                        onOpenChange={setFormOpen}
                        pact={editTarget}
                        employeeId={employeeId}
                    />

                    <ConfirmDialog
                        open={revokeTarget !== null}
                        onOpenChange={(open) => !open && setRevokeTarget(null)}
                        title={t('ui.overtime.pacts.revoke_dialog.title')}
                        description={t(
                            'ui.overtime.pacts.revoke_dialog.description',
                            { employee: employeeName },
                        )}
                        confirmLabel={t(
                            'ui.overtime.pacts.revoke_dialog.confirm',
                        )}
                        onConfirm={confirmRevoke}
                        processing={processingRow}
                    />
                </>
            )}
        </Card>
    );
}
