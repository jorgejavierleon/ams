import { FileSpreadsheet, FileText, FileType } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { exportMethod } from '@/routes/dt/reports';
import type { ReportFilters, ReportType } from './types';

/**
 * The three export formats every report must offer (Resolución 38, Art. 28 b),
 * in the order the on-screen buttons show them.
 */
const FORMATS: { format: 'excel' | 'pdf' | 'word'; icon: LucideIcon }[] = [
    { format: 'excel', icon: FileSpreadsheet },
    { format: 'pdf', icon: FileText },
    { format: 'word', icon: FileType },
];

type Props = {
    reportType: ReportType;
    filters: ReportFilters;
};

/**
 * Excel / PDF / Word download buttons shared by every DT report page. Each is a
 * plain anchor pointing at the export route with the current filters as query
 * params, so the browser streams the file directly without an Inertia visit or a
 * full page reload (Art. 28 b).
 */
export function ExportButtons({ reportType, filters }: Props) {
    const { t } = useTranslations();

    const href = (format: string) =>
        exportMethod(reportType, {
            query: {
                format,
                start: filters.start,
                end: filters.end,
                employees: filters.employees,
                positions: filters.positions,
                premises: filters.premises,
                journals: filters.journals,
                shifts: filters.shifts,
                checksum: filters.checksum ?? undefined,
            },
        }).url;

    return (
        <div className="flex items-center justify-end gap-2">
            {FORMATS.map(({ format, icon: Icon }) => (
                <Button key={format} asChild variant="outline" size="sm">
                    <a href={href(format)}>
                        <Icon className="size-4" />
                        {t(`ui.dt.reports.${reportType}.export.${format}`)}
                    </a>
                </Button>
            ))}
        </div>
    );
}
