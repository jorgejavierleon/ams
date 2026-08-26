import { Head } from '@inertiajs/react';
import {
    FileSpreadsheet,
    Timer,
    UserRoundCog,
    Users,
    Wallet,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';

type ReportType =
    | 'payroll-summary'
    | 'weekly-detail'
    | 'period-movements'
    | 'employee-master'
    | 'overtime-excess';

const REPORT_ICONS: Record<ReportType, typeof Wallet> = {
    'payroll-summary': Wallet,
    'weekly-detail': FileSpreadsheet,
    'period-movements': Users,
    'employee-master': UserRoundCog,
    'overtime-excess': Timer,
};

type Props = {
    reportTypes: ReportType[];
};

export default function PayrollReportsIndex({ reportTypes }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.payroll_reports.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.payroll_reports.title')}
                    description={t('ui.payroll_reports.description')}
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {reportTypes.map((type) => {
                        const Icon = REPORT_ICONS[type];

                        return (
                            <Card key={type}>
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-2">
                                        <CardTitle className="flex items-center gap-2 text-base">
                                            <Icon className="size-4 text-muted-foreground" />
                                            {t(
                                                `ui.payroll_reports.types.${type}`,
                                            )}
                                        </CardTitle>
                                        <Badge variant="secondary">
                                            {t(
                                                'ui.payroll_reports.coming_soon',
                                            )}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            `ui.payroll_reports.descriptions.${type}`,
                                        )}
                                    </p>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </div>
        </>
    );
}
