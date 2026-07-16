import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { create, destroy, edit, index } from '@/routes/saas/document-variables';
import type { Paginated } from '@/types/ui';

type DocumentVariable = {
    id: number;
    name: string;
    key: string;
    description: string | null;
    created_at: string | null;
};

type Props = {
    variables: Paginated<DocumentVariable>;
    filters: {
        search: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
};

export default function DocumentVariablesIndex({ variables, filters }: Props) {
    const { t } = useTranslations();
    const [deleteTarget, setDeleteTarget] = useState<DocumentVariable | null>(
        null,
    );

    const columns = useMemo<ColumnDef<DocumentVariable>[]>(
        () => [
            {
                accessorKey: 'name',
                meta: { title: t('ui.document_variables.columns.name') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.document_variables.columns.name')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-medium">{row.original.name}</span>
                ),
            },
            {
                accessorKey: 'key',
                meta: { title: t('ui.document_variables.columns.key') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.document_variables.columns.key')}
                    />
                ),
                cell: ({ row }) => (
                    <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-sm">
                        {row.original.key}
                    </code>
                ),
            },
            {
                accessorKey: 'description',
                enableSorting: false,
                meta: { title: t('ui.document_variables.columns.description') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.document_variables.columns.description')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="text-muted-foreground">
                        {row.original.description ?? '—'}
                    </span>
                ),
            },
            {
                accessorKey: 'created_at',
                meta: { title: t('ui.document_variables.columns.created') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.document_variables.columns.created')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="text-muted-foreground">
                        {row.original.created_at ?? '—'}
                    </span>
                ),
            },
            {
                id: 'actions',
                enableHiding: false,
                meta: {
                    headClassName: 'text-right',
                    cellClassName: 'text-right',
                },
                header: () => null,
                cell: ({ row }) => (
                    <div className="flex justify-end gap-2">
                        <Link
                            href={edit(row.original.id)}
                            className="text-sm text-primary underline-offset-4 hover:underline"
                        >
                            {t('ui.document_variables.actions.edit')}
                        </Link>
                        <button
                            type="button"
                            onClick={() => setDeleteTarget(row.original)}
                            className="text-sm text-destructive underline-offset-4 hover:underline"
                        >
                            {t('ui.document_variables.actions.delete')}
                        </button>
                    </div>
                ),
            },
        ],
        [t],
    );

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
            <Head title={t('ui.document_variables.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.document_variables.title')}
                        description={t('ui.document_variables.description')}
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            {t('ui.document_variables.new')}
                        </Link>
                    </Button>
                </div>

                <DataTable
                    data={variables}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    only={['variables', 'filters']}
                    searchPlaceholder={t(
                        'ui.document_variables.search_placeholder',
                    )}
                    emptyLabel={t('ui.document_variables.empty')}
                />
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title={t('ui.document_variables.delete_dialog.title')}
                description={t(
                    'ui.document_variables.delete_dialog.description',
                    {
                        name: deleteTarget?.name ?? '',
                    },
                )}
                confirmLabel={t('ui.document_variables.delete_dialog.confirm')}
                onConfirm={confirmDelete}
            />
        </>
    );
}
