import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Combobox } from '@/components/combobox';
import type { ComboboxOption } from '@/components/combobox';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import { create, destroy, edit, index, show } from '@/routes/documents';
import type { Paginated } from '@/types/ui';

type StatusBadge = {
    value: string;
    label: string;
    variant: 'default' | 'secondary' | 'destructive' | 'outline';
};

type DocumentRow = {
    id: number;
    title: string;
    type: string | null;
    employee: string | null;
    status: StatusBadge;
    published_at: string | null;
    signed_at: string | null;
};

type EnumOption = { value: string; label: string };

type Props = {
    documents: Paginated<DocumentRow>;
    filters: {
        search: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
        status: string | null;
        type: string | null;
        employee: string | null;
        from: string | null;
        to: string | null;
    };
    statusOptions: EnumOption[];
    typeOptions: EnumOption[];
    employeeOptions: ComboboxOption[];
};

export default function DocumentsIndex({
    documents,
    filters,
    statusOptions,
    typeOptions,
    employeeOptions,
}: Props) {
    const { t } = useTranslations();
    const [deleteTarget, setDeleteTarget] = useState<DocumentRow | null>(null);

    const [status, setStatus] = useState(filters.status ?? 'all');
    const [type, setType] = useState(filters.type ?? 'all');
    const [employee, setEmployee] = useState(filters.employee ?? '');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    const extraParams = useMemo(
        () => ({
            status: status === 'all' ? undefined : status,
            type: type === 'all' ? undefined : type,
            employee: employee || undefined,
            from: from || undefined,
            to: to || undefined,
        }),
        [status, type, employee, from, to],
    );

    const hasFilters =
        status !== 'all' ||
        type !== 'all' ||
        employee !== '' ||
        from !== '' ||
        to !== '';

    function clearFilters() {
        setStatus('all');
        setType('all');
        setEmployee('');
        setFrom('');
        setTo('');
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

    const columns = useMemo<ColumnDef<DocumentRow>[]>(
        () => [
            {
                accessorKey: 'title',
                meta: { title: t('ui.documents.columns.title') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.documents.columns.title')}
                    />
                ),
                cell: ({ row }) => (
                    <Link
                        href={show(row.original.id)}
                        className="font-medium text-primary underline-offset-4 hover:underline"
                    >
                        {row.original.title}
                    </Link>
                ),
            },
            {
                id: 'type',
                enableSorting: false,
                meta: { title: t('ui.documents.columns.type') },
                header: () => t('ui.documents.columns.type'),
                cell: ({ row }) => row.original.type ?? '—',
            },
            {
                id: 'employee',
                enableSorting: false,
                meta: { title: t('ui.documents.columns.employee') },
                header: () => t('ui.documents.columns.employee'),
                cell: ({ row }) => row.original.employee ?? '—',
            },
            {
                accessorKey: 'status',
                meta: { title: t('ui.documents.columns.status') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.documents.columns.status')}
                    />
                ),
                cell: ({ row }) => (
                    <Badge variant={row.original.status.variant}>
                        {row.original.status.label}
                    </Badge>
                ),
            },
            {
                accessorKey: 'published_at',
                meta: { title: t('ui.documents.columns.published_at') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.documents.columns.published_at')}
                    />
                ),
                cell: ({ row }) => row.original.published_at ?? '—',
            },
            {
                accessorKey: 'signed_at',
                meta: { title: t('ui.documents.columns.signed_at') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.documents.columns.signed_at')}
                    />
                ),
                cell: ({ row }) => row.original.signed_at ?? '—',
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
                            {t('ui.documents.actions.edit')}
                        </Link>
                        <button
                            type="button"
                            onClick={() => setDeleteTarget(row.original)}
                            className="text-sm text-destructive underline-offset-4 hover:underline"
                        >
                            {t('ui.documents.actions.delete')}
                        </button>
                    </div>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('ui.documents.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.documents.title')}
                        description={t('ui.documents.description')}
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            {t('ui.documents.new')}
                        </Link>
                    </Button>
                </div>

                <DataTable
                    data={documents}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    extraParams={extraParams}
                    only={['documents', 'filters']}
                    searchPlaceholder={t('ui.documents.search_placeholder')}
                    emptyLabel={t('ui.documents.empty')}
                    toolbar={
                        <div className="flex flex-wrap items-center gap-2">
                            <Select value={status} onValueChange={setStatus}>
                                <SelectTrigger className="w-[160px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        {t('ui.documents.filters.status_all')}
                                    </SelectItem>
                                    {statusOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <Select value={type} onValueChange={setType}>
                                <SelectTrigger className="w-[160px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        {t('ui.documents.filters.type_all')}
                                    </SelectItem>
                                    {typeOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            <div className="w-[200px]">
                                <Combobox
                                    options={employeeOptions}
                                    value={employee}
                                    onChange={setEmployee}
                                    placeholder={t(
                                        'ui.documents.filters.employee',
                                    )}
                                    searchPlaceholder={t('ui.common.search')}
                                    emptyLabel={t('ui.common.no_results')}
                                />
                            </div>

                            <Input
                                type="date"
                                value={from}
                                onChange={(e) => setFrom(e.target.value)}
                                className="w-[150px]"
                                aria-label={t('ui.documents.filters.from')}
                            />
                            <Input
                                type="date"
                                value={to}
                                onChange={(e) => setTo(e.target.value)}
                                className="w-[150px]"
                                aria-label={t('ui.documents.filters.to')}
                            />

                            {hasFilters && (
                                <Button
                                    variant="ghost"
                                    onClick={clearFilters}
                                    className="px-2"
                                >
                                    {t('ui.documents.filters.clear')}
                                </Button>
                            )}
                        </div>
                    }
                />
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title={t('ui.documents.delete_dialog.title')}
                description={t('ui.documents.delete_dialog.description', {
                    title: deleteTarget?.title ?? '',
                })}
                confirmLabel={t('ui.documents.delete_dialog.confirm')}
                onConfirm={confirmDelete}
            />
        </>
    );
}
