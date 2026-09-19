import { Badge } from '@/components/ui/badge';
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

export type EmployeeLeave = {
    id: number;
    type: { value: string; label: string };
    start_date: string;
    end_date: string;
    business_days_requested: number;
    status: { value: string; label: string; badge: string };
};

type Props = {
    leaves: EmployeeLeave[];
};

const STATUS_BADGE_VARIANT: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    success: 'default',
    warning: 'secondary',
    destructive: 'destructive',
};

export function EmployeeLeaves({ leaves }: Props) {
    const { t } = useTranslations();

    return (
        <Card>
            <CardContent className="space-y-4 pt-6">
                <h2 className="text-sm font-medium text-muted-foreground">
                    {t('ui.employees.show.leaves.title')}
                </h2>

                {leaves.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        {t('ui.employees.show.leaves.empty')}
                    </p>
                ) : (
                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t(
                                            'ui.employees.show.leaves.columns.type',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.employees.show.leaves.columns.start_date',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.employees.show.leaves.columns.end_date',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.employees.show.leaves.columns.days',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.employees.show.leaves.columns.status',
                                        )}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {leaves.map((leave) => (
                                    <TableRow key={leave.id}>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {leave.type.label}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {leave.start_date}
                                        </TableCell>
                                        <TableCell>{leave.end_date}</TableCell>
                                        <TableCell>
                                            {leave.business_days_requested}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={
                                                    STATUS_BADGE_VARIANT[
                                                        leave.status.badge
                                                    ] ?? 'outline'
                                                }
                                            >
                                                {leave.status.label}
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
