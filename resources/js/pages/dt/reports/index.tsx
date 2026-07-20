import { Head } from '@inertiajs/react';
import { FileBarChart } from 'lucide-react';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';
import { FilterForm } from './filter-form';
import type { ReportFilters, ReportOptions, ReportType } from './types';

type Props = {
    /** The report type of the current route, or null on the landing page. */
    reportType: ReportType | null;
    options: ReportOptions;
    filters: ReportFilters;
};

export default function ReportsIndex({ reportType, options, filters }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.dt.reports.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.dt.reports.title')}
                    description={t('ui.dt.reports.description')}
                />

                <Card>
                    <CardContent>
                        <FilterForm
                            options={options}
                            filters={filters}
                            reportType={reportType}
                        />
                    </CardContent>
                </Card>

                {reportType && (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-3 py-16 text-center">
                            <FileBarChart className="size-10 text-muted-foreground" />
                            <p className="text-sm font-medium">
                                {t(`ui.dt.reports.types.${reportType}`)}
                            </p>
                            <p className="max-w-sm text-sm text-muted-foreground">
                                {t('ui.dt.reports.coming_soon')}
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
