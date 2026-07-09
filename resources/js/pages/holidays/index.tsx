import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Lock, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import HolidayFormDialog from '@/components/holiday-form-dialog';
import type { HolidayFormTarget } from '@/components/holiday-form-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { destroy, index } from '@/routes/holidays';

type Holiday = {
    id: number;
    name: string;
    date: string;
    mandatory: boolean;
    is_official: boolean;
};

type Props = {
    holidays: { data: Holiday[] };
    filters: {
        search: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
};

export default function HolidaysIndex({ holidays, filters }: Props) {
    const { t, formatDate } = useTranslations();
    const [formOpen, setFormOpen] = useState(false);
    const [editTarget, setEditTarget] = useState<HolidayFormTarget>(null);
    const [deleteTarget, setDeleteTarget] = useState<Holiday | null>(null);

    const columns = useMemo<ColumnDef<Holiday>[]>(
        () => [
            {
                accessorKey: 'date',
                meta: { title: t('ui.holidays.columns.date') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.holidays.columns.date')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-medium">
                        {/* Append time so the date-only value parses in local time (no UTC shift). */}
                        {formatDate(`${row.original.date}T00:00:00`)}
                    </span>
                ),
            },
            {
                accessorKey: 'name',
                meta: { title: t('ui.holidays.columns.name') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.holidays.columns.name')}
                    />
                ),
                cell: ({ row }) => row.original.name,
            },
            {
                accessorKey: 'is_official',
                enableSorting: false,
                meta: { title: t('ui.holidays.columns.type') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.holidays.columns.type')}
                    />
                ),
                cell: ({ row }) =>
                    row.original.is_official ? (
                        <Badge variant="outline" className="gap-1">
                            <Lock className="size-3" />
                            {t('ui.holidays.official')}
                        </Badge>
                    ) : (
                        <Badge variant="secondary">
                            {t('ui.holidays.custom')}
                        </Badge>
                    ),
            },
            {
                accessorKey: 'mandatory',
                meta: { title: t('ui.holidays.columns.mandatory') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.holidays.columns.mandatory')}
                    />
                ),
                cell: ({ row }) => (
                    <Badge
                        variant={
                            row.original.mandatory ? 'default' : 'secondary'
                        }
                    >
                        {t(
                            row.original.mandatory
                                ? 'ui.holidays.yes'
                                : 'ui.holidays.no',
                        )}
                    </Badge>
                ),
            },
            {
                id: 'actions',
                enableHiding: false,
                meta: { headClassName: 'text-right', cellClassName: 'text-right' },
                header: () => null,
                cell: ({ row }) => {
                    // Official holidays are shared and cannot be edited by tenants.
                    if (row.original.is_official) {
                        return null;
                    }

                    return (
                        <div className="flex justify-end gap-2">
                            <button
                                type="button"
                                onClick={() => {
                                    setEditTarget({
                                        id: row.original.id,
                                        name: row.original.name,
                                        date: row.original.date,
                                        mandatory: row.original.mandatory,
                                    });
                                    setFormOpen(true);
                                }}
                                className="text-sm text-primary underline-offset-4 hover:underline"
                            >
                                {t('ui.holidays.actions.edit')}
                            </button>
                            <button
                                type="button"
                                onClick={() => setDeleteTarget(row.original)}
                                className="text-sm text-destructive underline-offset-4 hover:underline"
                            >
                                {t('ui.holidays.actions.delete')}
                            </button>
                        </div>
                    );
                },
            },
        ],
        [t, formatDate],
    );

    function openCreate() {
        setEditTarget(null);
        setFormOpen(true);
    }

    function confirmDelete() {
        if (!deleteTarget) {
            return;
        }

        router.delete(destroy(deleteTarget.id).url, {
            preserveScroll: true,
            onFinish: () => setDeleteTarget(null),
        });
    }

    return (
        <>
            <Head title={t('ui.holidays.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.holidays.title')}
                        description={t('ui.holidays.description')}
                    />
                    <Button onClick={openCreate}>
                        <Plus className="size-4" />
                        {t('ui.holidays.new')}
                    </Button>
                </div>

                <DataTable
                    data={holidays}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    only={['holidays', 'filters']}
                    searchPlaceholder={t('ui.holidays.search_placeholder')}
                    emptyLabel={t('ui.holidays.empty')}
                    showPagination={false}
                />
            </div>

            <HolidayFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                holiday={editTarget}
            />

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title={t('ui.holidays.delete_dialog.title')}
                description={t('ui.holidays.delete_dialog.description', {
                    name: deleteTarget?.name ?? '',
                })}
                confirmLabel={t('ui.holidays.delete_dialog.confirm')}
                onConfirm={confirmDelete}
            />
        </>
    );
}
