import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import {
    create,
    destroy,
    edit,
    index,
    restore,
} from '@/routes/document-templates';
import type { Paginated } from '@/types/ui';

type TemplateRow = {
    id: number;
    title: string;
    type: string | null;
    variable_count: number;
    updated_at: string | null;
    trashed: boolean;
};

type Props = {
    templates: Paginated<TemplateRow>;
    filters: {
        search: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
};

export default function DocumentTemplatesIndex({ templates, filters }: Props) {
    const { t } = useTranslations();
    const [deleteTarget, setDeleteTarget] = useState<TemplateRow | null>(null);

    function confirmDelete() {
        if (!deleteTarget) {
            return;
        }

        router.delete(destroy(deleteTarget.id).url, {
            preserveScroll: true,
            onFinish: () => setDeleteTarget(null),
        });
    }

    function restoreTemplate(template: TemplateRow) {
        router.patch(restore(template.id).url, {}, { preserveScroll: true });
    }

    const columns = useMemo<ColumnDef<TemplateRow>[]>(
        () => [
            {
                accessorKey: 'title',
                meta: { title: t('ui.document_templates.columns.title') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.document_templates.columns.title')}
                    />
                ),
                cell: ({ row }) =>
                    row.original.trashed ? (
                        <span className="font-medium text-muted-foreground">
                            {row.original.title}
                        </span>
                    ) : (
                        <Link
                            href={edit(row.original.id)}
                            className="font-medium text-primary underline-offset-4 hover:underline"
                        >
                            {row.original.title}
                        </Link>
                    ),
            },
            {
                id: 'type',
                enableSorting: false,
                meta: { title: t('ui.document_templates.columns.type') },
                header: () => t('ui.document_templates.columns.type'),
                cell: ({ row }) => row.original.type ?? '—',
            },
            {
                id: 'variable_count',
                enableSorting: false,
                meta: { title: t('ui.document_templates.columns.variables') },
                header: () => t('ui.document_templates.columns.variables'),
                cell: ({ row }) => row.original.variable_count,
            },
            {
                accessorKey: 'updated_at',
                meta: { title: t('ui.document_templates.columns.updated_at') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.document_templates.columns.updated_at')}
                    />
                ),
                cell: ({ row }) => row.original.updated_at ?? '—',
            },
            {
                id: 'state',
                enableSorting: false,
                meta: { title: t('ui.document_templates.columns.state') },
                header: () => t('ui.document_templates.columns.state'),
                cell: ({ row }) =>
                    row.original.trashed ? (
                        <Badge variant="destructive">
                            {t('ui.document_templates.state.deleted')}
                        </Badge>
                    ) : (
                        <Badge variant="secondary">
                            {t('ui.document_templates.state.active')}
                        </Badge>
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
                        {row.original.trashed ? (
                            <button
                                type="button"
                                onClick={() => restoreTemplate(row.original)}
                                className="text-sm text-primary underline-offset-4 hover:underline"
                            >
                                {t('ui.document_templates.actions.restore')}
                            </button>
                        ) : (
                            <>
                                <Link
                                    href={edit(row.original.id)}
                                    className="text-sm text-primary underline-offset-4 hover:underline"
                                >
                                    {t('ui.document_templates.actions.edit')}
                                </Link>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setDeleteTarget(row.original)
                                    }
                                    className="text-sm text-destructive underline-offset-4 hover:underline"
                                >
                                    {t('ui.document_templates.actions.delete')}
                                </button>
                            </>
                        )}
                    </div>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('ui.document_templates.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.document_templates.title')}
                        description={t('ui.document_templates.description')}
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            {t('ui.document_templates.new')}
                        </Link>
                    </Button>
                </div>

                <DataTable
                    data={templates}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    only={['templates', 'filters']}
                    searchPlaceholder={t(
                        'ui.document_templates.search_placeholder',
                    )}
                    emptyLabel={t('ui.document_templates.empty')}
                />
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title={t('ui.document_templates.delete_dialog.title')}
                description={t(
                    'ui.document_templates.delete_dialog.description',
                    {
                        title: deleteTarget?.title ?? '',
                    },
                )}
                confirmLabel={t('ui.document_templates.delete_dialog.confirm')}
                onConfirm={confirmDelete}
            />
        </>
    );
}
