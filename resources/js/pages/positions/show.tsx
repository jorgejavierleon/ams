import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
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
import { index } from '@/routes/positions';

type Employee = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
};

type Paginated<T> = {
    data: T[];
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Props = {
    position: {
        id: number;
        name: string;
        active_users_count: number;
    };
    employees: Paginated<Employee>;
};

export default function PositionShow({ position, employees }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={position.name} />

            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between gap-4">
                    <Heading
                        title={position.name}
                        description={t('ui.positions.employees.title')}
                    />
                    <Button variant="outline" asChild>
                        <Link href={index()}>
                            <ArrowLeft className="size-4" />
                            {t('ui.positions.back')}
                        </Link>
                    </Button>
                </div>

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    {t('ui.positions.employees.columns.name')}
                                </TableHead>
                                <TableHead>
                                    {t('ui.positions.employees.columns.email')}
                                </TableHead>
                                <TableHead>
                                    {t('ui.positions.employees.columns.status')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {employees.data.map((employee) => (
                                <TableRow key={employee.id}>
                                    <TableCell className="font-medium">
                                        {employee.name}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {employee.email}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                employee.is_active
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {t(
                                                employee.is_active
                                                    ? 'ui.positions.employees.status.active'
                                                    : 'ui.positions.employees.status.inactive',
                                            )}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {employees.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={3}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        {t('ui.positions.employees.empty')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-sm text-muted-foreground">
                        {employees.total > 0
                            ? t('ui.positions.pagination.showing', {
                                  from: employees.from ?? 0,
                                  to: employees.to ?? 0,
                                  total: employees.total,
                              })
                            : t('ui.positions.pagination.none')}
                    </p>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!employees.prev_page_url}
                            asChild={Boolean(employees.prev_page_url)}
                        >
                            {employees.prev_page_url ? (
                                <Link
                                    href={employees.prev_page_url}
                                    preserveScroll
                                    preserveState
                                >
                                    {t('ui.positions.pagination.previous')}
                                </Link>
                            ) : (
                                <span>
                                    {t('ui.positions.pagination.previous')}
                                </span>
                            )}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={!employees.next_page_url}
                            asChild={Boolean(employees.next_page_url)}
                        >
                            {employees.next_page_url ? (
                                <Link
                                    href={employees.next_page_url}
                                    preserveScroll
                                    preserveState
                                >
                                    {t('ui.positions.pagination.next')}
                                </Link>
                            ) : (
                                <span>{t('ui.positions.pagination.next')}</span>
                            )}
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
