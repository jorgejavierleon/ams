import { router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { store } from '@/actions/App/Http/Controllers/ShiftAssignmentController';
import { Combobox } from '@/components/combobox';
import type { ComboboxOption } from '@/components/combobox';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FormField } from '@/components/form-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslations } from '@/hooks/use-translations';
import { end, destroy } from '@/routes/shift-assignments';

type AssignmentStatus = 'current' | 'ended' | 'upcoming';

export type ShiftAssignment = {
    id: number;
    shift: string | null;
    start_date: string;
    end_date: string | null;
    status: AssignmentStatus;
};

const STATUS_VARIANT: Record<
    AssignmentStatus,
    'default' | 'secondary' | 'outline'
> = {
    current: 'default',
    upcoming: 'secondary',
    ended: 'outline',
};

type Props = {
    employeeId: number;
    assignments: ShiftAssignment[];
    shiftOptions: ComboboxOption[];
};

type AddForm = {
    shift_id: string;
    start_date: string;
    end_date: string;
};

export function ShiftAssignments({
    employeeId,
    assignments,
    shiftOptions,
}: Props) {
    const { t } = useTranslations();

    const [addOpen, setAddOpen] = useState(false);
    const [endTarget, setEndTarget] = useState<ShiftAssignment | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<ShiftAssignment | null>(
        null,
    );
    const [processingRow, setProcessingRow] = useState(false);

    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm<AddForm>({
            shift_id: '',
            start_date: '',
            end_date: '',
        });

    function openAdd() {
        reset();
        clearErrors();
        setAddOpen(true);
    }

    function submitAdd(event: FormEvent) {
        event.preventDefault();

        post(store({ employee: employeeId }).url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setAddOpen(false);
            },
        });
    }

    function confirmEnd() {
        if (!endTarget) {
            return;
        }

        router.patch(
            end(endTarget.id).url,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessingRow(true),
                onFinish: () => setProcessingRow(false),
                onSuccess: () => setEndTarget(null),
            },
        );
    }

    function confirmDelete() {
        if (!deleteTarget) {
            return;
        }

        router.delete(destroy(deleteTarget.id).url, {
            preserveScroll: true,
            onStart: () => setProcessingRow(true),
            onFinish: () => setProcessingRow(false),
            onSuccess: () => setDeleteTarget(null),
        });
    }

    return (
        <Card>
            <CardContent className="space-y-4 pt-6">
                <div className="flex items-center justify-between gap-4">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        {t('ui.shifts.shift_assignments.title')}
                    </h2>
                    <Button size="sm" onClick={openAdd}>
                        <Plus className="size-4" />
                        {t('ui.shifts.shift_assignments.add')}
                    </Button>
                </div>

                {assignments.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        {t('ui.shifts.shift_assignments.empty')}
                    </p>
                ) : (
                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t(
                                            'ui.shifts.shift_assignments.columns.shift',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.shifts.shift_assignments.columns.start_date',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.shifts.shift_assignments.columns.end_date',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.shifts.shift_assignments.columns.status',
                                        )}
                                    </TableHead>
                                    <TableHead className="w-0" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {assignments.map((assignment) => (
                                    <TableRow key={assignment.id}>
                                        <TableCell className="font-medium">
                                            {assignment.shift ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {assignment.start_date}
                                        </TableCell>
                                        <TableCell>
                                            {assignment.end_date ?? (
                                                <span className="text-muted-foreground">
                                                    {t(
                                                        'ui.shifts.shift_assignments.permanent',
                                                    )}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    STATUS_VARIANT[
                                                        assignment.status
                                                    ]
                                                }
                                            >
                                                {t(
                                                    `ui.shifts.shift_assignments.status_${assignment.status}`,
                                                )}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                {assignment.status ===
                                                    'current' &&
                                                    !assignment.end_date && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                setEndTarget(
                                                                    assignment,
                                                                )
                                                            }
                                                        >
                                                            {t(
                                                                'ui.shifts.shift_assignments.actions.end',
                                                            )}
                                                        </Button>
                                                    )}
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() =>
                                                        setDeleteTarget(
                                                            assignment,
                                                        )
                                                    }
                                                >
                                                    {t(
                                                        'ui.shifts.shift_assignments.actions.delete',
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
            </CardContent>

            <Dialog open={addOpen} onOpenChange={setAddOpen}>
                <DialogContent>
                    <form onSubmit={submitAdd} noValidate>
                        <DialogHeader>
                            <DialogTitle>
                                {t('ui.shifts.shift_assignments.dialog.title')}
                            </DialogTitle>
                            <DialogDescription>
                                {t(
                                    'ui.shifts.shift_assignments.dialog.description',
                                )}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-4 py-4">
                            <FormField
                                label={t(
                                    'ui.shifts.shift_assignments.dialog.shift',
                                )}
                                htmlFor="shift_id"
                                required
                                error={errors.shift_id}
                            >
                                <Combobox
                                    id="shift_id"
                                    modal
                                    options={shiftOptions}
                                    value={data.shift_id}
                                    onChange={(value) =>
                                        setData('shift_id', value)
                                    }
                                    placeholder={t(
                                        'ui.shifts.shift_assignments.dialog.shift_placeholder',
                                    )}
                                    searchPlaceholder={t(
                                        'ui.shifts.shift_assignments.dialog.shift_search',
                                    )}
                                    emptyLabel={t(
                                        'ui.shifts.shift_assignments.dialog.shift_empty',
                                    )}
                                />
                            </FormField>

                            <FormField
                                label={t(
                                    'ui.shifts.shift_assignments.dialog.start_date',
                                )}
                                htmlFor="start_date"
                                required
                                error={errors.start_date}
                            >
                                <Input
                                    id="start_date"
                                    type="date"
                                    value={data.start_date}
                                    onChange={(e) =>
                                        setData('start_date', e.target.value)
                                    }
                                />
                            </FormField>

                            <FormField
                                label={t(
                                    'ui.shifts.shift_assignments.dialog.end_date',
                                )}
                                htmlFor="end_date"
                                error={errors.end_date}
                            >
                                <Input
                                    id="end_date"
                                    type="date"
                                    value={data.end_date}
                                    onChange={(e) =>
                                        setData('end_date', e.target.value)
                                    }
                                />
                            </FormField>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setAddOpen(false)}
                                disabled={processing}
                            >
                                {t('ui.shifts.shift_assignments.dialog.cancel')}
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                {t('ui.shifts.shift_assignments.dialog.submit')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={endTarget !== null}
                onOpenChange={(open) => !open && setEndTarget(null)}
                title={t('ui.shifts.shift_assignments.end_dialog.title')}
                description={t(
                    'ui.shifts.shift_assignments.end_dialog.description',
                )}
                confirmLabel={t(
                    'ui.shifts.shift_assignments.end_dialog.confirm',
                )}
                variant="default"
                onConfirm={confirmEnd}
                processing={processingRow}
            />

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title={t('ui.shifts.shift_assignments.delete_dialog.title')}
                description={t(
                    'ui.shifts.shift_assignments.delete_dialog.description',
                )}
                confirmLabel={t(
                    'ui.shifts.shift_assignments.delete_dialog.confirm',
                )}
                onConfirm={confirmDelete}
                processing={processingRow}
            />
        </Card>
    );
}
