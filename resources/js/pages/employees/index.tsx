import { Head, Link, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import { DataTableFacetedFilter } from '@/components/data-table-faceted-filter';
import type { FacetedOption } from '@/components/data-table-faceted-filter';
import Heading from '@/components/heading';
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import {
    create,
    destroy,
    edit,
    index,
    show,
    toggleActive,
} from '@/routes/employees';
import type { Paginated } from '@/types/ui';

type Employee = {
    id: number;
    name: string;
    email: string;
    rut: string | null;
    avatar: string | null;
    position: string | null;
    premise: string | null;
    is_active: boolean;
    is_admin: boolean;
};

type Props = {
    employees: Paginated<Employee>;
    filters: {
        search: string | null;
        sort: string | null;
        direction: 'asc' | 'desc' | null;
        is_active: string | null;
        is_admin: string | null;
        premises: string[];
        positions: string[];
    };
    premiseOptions: FacetedOption[];
    positionOptions: FacetedOption[];
};

export default function EmployeesIndex({
    employees,
    filters,
    premiseOptions,
    positionOptions,
}: Props) {
    const { t } = useTranslations();
    const [deleteTarget, setDeleteTarget] = useState<Employee | null>(null);

    const [isActive, setIsActive] = useState(filters.is_active ?? 'all');
    const [isAdmin, setIsAdmin] = useState(filters.is_admin ?? 'all');
    const [premises, setPremises] = useState<string[]>(filters.premises ?? []);
    const [positions, setPositions] = useState<string[]>(
        filters.positions ?? [],
    );

    const extraParams = useMemo(
        () => ({
            is_active: isActive === 'all' ? undefined : isActive,
            is_admin: isAdmin === 'all' ? undefined : isAdmin,
            premises: premises.length > 0 ? premises : undefined,
            positions: positions.length > 0 ? positions : undefined,
        }),
        [isActive, isAdmin, premises, positions],
    );

    const hasFilters =
        isActive !== 'all' ||
        isAdmin !== 'all' ||
        premises.length > 0 ||
        positions.length > 0;

    function clearFilters() {
        setIsActive('all');
        setIsAdmin('all');
        setPremises([]);
        setPositions([]);
    }

    function toggleEmployeeActive(employee: Employee) {
        router.patch(
            toggleActive(employee.id).url,
            {},
            { preserveScroll: true, preserveState: true },
        );
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

    const columns = useMemo<ColumnDef<Employee>[]>(
        () => [
            {
                accessorKey: 'name',
                meta: { title: t('ui.employees.columns.employee') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.employees.columns.employee')}
                    />
                ),
                cell: ({ row }) => (
                    <Link
                        href={show(row.original.id)}
                        className="flex items-center gap-3"
                    >
                        <Avatar className="size-8">
                            {row.original.avatar ? (
                                <AvatarImage src={row.original.avatar} alt="" />
                            ) : null}
                            <AvatarFallback>
                                {row.original.name.charAt(0).toUpperCase()}
                            </AvatarFallback>
                        </Avatar>
                        <span className="font-medium text-primary underline-offset-4 hover:underline">
                            {row.original.name}
                        </span>
                    </Link>
                ),
            },
            {
                accessorKey: 'email',
                meta: { title: t('ui.employees.columns.email') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.employees.columns.email')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="text-muted-foreground">
                        {row.original.email}
                    </span>
                ),
            },
            {
                accessorKey: 'rut',
                meta: { title: t('ui.employees.columns.rut') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.employees.columns.rut')}
                    />
                ),
                cell: ({ row }) => row.original.rut ?? '—',
            },
            {
                id: 'position',
                enableSorting: false,
                meta: { title: t('ui.employees.columns.position') },
                header: () => t('ui.employees.columns.position'),
                cell: ({ row }) => row.original.position ?? '—',
            },
            {
                id: 'premise',
                enableSorting: false,
                meta: { title: t('ui.employees.columns.premise') },
                header: () => t('ui.employees.columns.premise'),
                cell: ({ row }) => row.original.premise ?? '—',
            },
            {
                id: 'is_admin',
                enableSorting: false,
                meta: { title: t('ui.employees.columns.is_admin') },
                header: () => t('ui.employees.columns.is_admin'),
                cell: ({ row }) =>
                    row.original.is_admin ? (
                        <Badge variant="secondary">
                            {t('ui.employees.columns.admin_badge')}
                        </Badge>
                    ) : null,
            },
            {
                id: 'is_active',
                enableSorting: false,
                meta: { title: t('ui.employees.columns.is_active') },
                header: () => t('ui.employees.columns.is_active'),
                cell: ({ row }) => (
                    <Checkbox
                        checked={row.original.is_active}
                        onCheckedChange={() =>
                            toggleEmployeeActive(row.original)
                        }
                        aria-label={t('ui.employees.columns.is_active')}
                    />
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
                            {t('ui.employees.actions.edit')}
                        </Link>
                        <button
                            type="button"
                            onClick={() => setDeleteTarget(row.original)}
                            className="text-sm text-destructive underline-offset-4 hover:underline"
                        >
                            {t('ui.employees.actions.delete')}
                        </button>
                    </div>
                ),
            },
        ],
        [t],
    );

    return (
        <>
            <Head title={t('ui.employees.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={t('ui.employees.title')}
                        description={t('ui.employees.description')}
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus className="size-4" />
                            {t('ui.employees.new')}
                        </Link>
                    </Button>
                </div>

                <DataTable
                    data={employees}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    extraParams={extraParams}
                    only={['employees', 'filters']}
                    searchPlaceholder={t('ui.employees.search_placeholder')}
                    emptyLabel={t('ui.employees.empty')}
                    toolbar={
                        <div className="flex flex-wrap items-center gap-2">
                            <Select value={isActive} onValueChange={setIsActive}>
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        {t('ui.employees.filters.active_all')}
                                    </SelectItem>
                                    <SelectItem value="1">
                                        {t('ui.employees.filters.active_yes')}
                                    </SelectItem>
                                    <SelectItem value="0">
                                        {t('ui.employees.filters.active_no')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            <Select value={isAdmin} onValueChange={setIsAdmin}>
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        {t('ui.employees.filters.admin_all')}
                                    </SelectItem>
                                    <SelectItem value="1">
                                        {t('ui.employees.filters.admin_yes')}
                                    </SelectItem>
                                    <SelectItem value="0">
                                        {t('ui.employees.filters.admin_no')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>

                            <DataTableFacetedFilter
                                title={t('ui.employees.filters.premise')}
                                options={premiseOptions}
                                selected={premises}
                                onChange={setPremises}
                            />

                            <DataTableFacetedFilter
                                title={t('ui.employees.filters.position')}
                                options={positionOptions}
                                selected={positions}
                                onChange={setPositions}
                            />

                            {hasFilters && (
                                <Button
                                    variant="ghost"
                                    onClick={clearFilters}
                                    className="px-2"
                                >
                                    {t('ui.employees.filters.clear')}
                                </Button>
                            )}
                        </div>
                    }
                />
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                title={t('ui.employees.delete_dialog.title')}
                description={t('ui.employees.delete_dialog.description', {
                    name: deleteTarget?.name ?? '',
                })}
                confirmLabel={t('ui.employees.delete_dialog.confirm')}
                onConfirm={confirmDelete}
            />
        </>
    );
}
