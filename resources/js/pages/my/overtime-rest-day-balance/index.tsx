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
import { index as overtimeIndex } from '@/routes/overtime';

type RestDayBalanceLine = {
    id: number;
    accrued_hours: string;
    rest_hours: string;
    consumed_hours: string;
    remaining_hours: string;
    accrual_date: string;
    expiry_date: string;
    status: {
        value: string;
        label: string;
        variant: 'default' | 'secondary' | 'destructive' | 'outline';
    };
};

type Props = {
    available: string;
    lines: RestDayBalanceLine[];
};

export default function MyOvertimeRestDayBalanceIndex({
    available,
    lines,
}: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.overtime.rest_day_balances.my.title')} />

            <div className="space-y-6 p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={overtimeIndex()}>
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <Heading
                        title={t('ui.overtime.rest_day_balances.my.title')}
                        description={t(
                            'ui.overtime.rest_day_balances.my.description',
                        )}
                    />
                </div>

                <div className="rounded-lg border p-5">
                    <p className="text-sm text-muted-foreground">
                        {t('ui.overtime.rest_day_balances.my.available')}
                    </p>
                    <p className="text-3xl font-semibold tabular-nums">
                        {available}
                    </p>
                </div>

                {lines.length === 0 ? (
                    <div className="flex items-center justify-center rounded-lg border border-dashed p-12 text-center text-sm text-muted-foreground">
                        {t('ui.overtime.rest_day_balances.my.empty')}
                    </div>
                ) : (
                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t(
                                            'ui.overtime.rest_day_balances.my.columns.accrual_date',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.overtime.rest_day_balances.my.columns.accrued_hours',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.overtime.rest_day_balances.my.columns.rest_hours',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.overtime.rest_day_balances.my.columns.consumed_hours',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.overtime.rest_day_balances.my.columns.remaining_hours',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.overtime.rest_day_balances.my.columns.expiry_date',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.overtime.rest_day_balances.my.columns.status',
                                        )}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {lines.map((line) => (
                                    <TableRow key={line.id}>
                                        <TableCell>
                                            {line.accrual_date}
                                        </TableCell>
                                        <TableCell>
                                            {line.accrued_hours}
                                        </TableCell>
                                        <TableCell>
                                            {line.rest_hours}
                                        </TableCell>
                                        <TableCell>
                                            {line.consumed_hours}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {line.remaining_hours}
                                        </TableCell>
                                        <TableCell>
                                            {line.expiry_date}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    line.status.variant
                                                }
                                            >
                                                {line.status.label}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}
