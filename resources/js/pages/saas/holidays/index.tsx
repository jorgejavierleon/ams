import { Head, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Download } from 'lucide-react';
import { useMemo } from 'react';
import type { FormEvent } from 'react';
import { DataTable } from '@/components/data-table';
import { DataTableColumnHeader } from '@/components/data-table-column-header';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { index, sync } from '@/routes/saas/holidays';

type Holiday = {
    id: number;
    name: string;
    date: string;
    mandatory: boolean;
};

type Props = {
    holidays: { data: Holiday[] };
    filters: {
        sort: string | null;
        direction: 'asc' | 'desc' | null;
    };
    currentYear: number;
};

export default function SaasHolidaysIndex({
    holidays,
    filters,
    currentYear,
}: Props) {
    const { t, formatDate } = useTranslations();
    const { data, setData, post, processing, errors } = useForm<{
        year: number;
    }>({ year: currentYear });

    function submitImport(event: FormEvent) {
        event.preventDefault();
        post(sync().url, { preserveScroll: true });
    }

    const columns = useMemo<ColumnDef<Holiday>[]>(
        () => [
            {
                accessorKey: 'date',
                meta: { title: t('ui.saas_holidays.columns.date') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.saas_holidays.columns.date')}
                    />
                ),
                cell: ({ row }) => (
                    <span className="font-medium">
                        {formatDate(`${row.original.date}T00:00:00`)}
                    </span>
                ),
            },
            {
                accessorKey: 'name',
                meta: { title: t('ui.saas_holidays.columns.name') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.saas_holidays.columns.name')}
                    />
                ),
                cell: ({ row }) => row.original.name,
            },
            {
                accessorKey: 'mandatory',
                meta: { title: t('ui.saas_holidays.columns.mandatory') },
                header: ({ column }) => (
                    <DataTableColumnHeader
                        column={column}
                        title={t('ui.saas_holidays.columns.mandatory')}
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
                                ? 'ui.saas_holidays.yes'
                                : 'ui.saas_holidays.no',
                        )}
                    </Badge>
                ),
            },
        ],
        [t, formatDate],
    );

    return (
        <>
            <Head title={t('ui.saas_holidays.title')} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <Heading
                        title={t('ui.saas_holidays.title')}
                        description={t('ui.saas_holidays.description')}
                    />
                    <form
                        onSubmit={submitImport}
                        className="flex items-end gap-2"
                    >
                        <div className="grid gap-1.5">
                            <Label htmlFor="year">
                                {t('ui.saas_holidays.import.year')}
                            </Label>
                            <Input
                                id="year"
                                type="number"
                                min={2000}
                                max={2100}
                                value={data.year}
                                onChange={(e) =>
                                    setData('year', Number(e.target.value))
                                }
                                className="w-28"
                            />
                        </div>
                        <Button type="submit" disabled={processing}>
                            {processing ? (
                                <Spinner />
                            ) : (
                                <Download className="size-4" />
                            )}
                            {t('ui.saas_holidays.import.submit')}
                        </Button>
                    </form>
                </div>

                {errors.year && (
                    <p className="text-sm text-destructive">{errors.year}</p>
                )}

                <DataTable
                    data={holidays}
                    columns={columns}
                    routeUrl={index().url}
                    filters={filters}
                    only={['holidays', 'filters']}
                    emptyLabel={t('ui.saas_holidays.empty')}
                    showPagination={false}
                />
            </div>
        </>
    );
}
