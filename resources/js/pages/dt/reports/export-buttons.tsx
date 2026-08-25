import { FileSpreadsheet, FileText, FileType } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
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
 * Read the filename `download()` sent in `Content-Disposition`, falling back
 * to a generic name if it is ever missing.
 */
function filenameFrom(response: Response): string {
    const match = /filename="?([^"]+)"?/.exec(
        response.headers.get('content-disposition') ?? '',
    );

    return match?.[1] ?? 'export';
}

/**
 * Excel / PDF / Word download buttons shared by every DT report page. Below
 * the queue threshold (KOL-16) the export still returns synchronously, so the
 * button just downloads the response; above it, the server queues the export
 * and replies with JSON instead, so the button surfaces a toast rather than
 * leaving the user staring at a stalled download (Art. 28 b).
 */
export function ExportButtons({ reportType, filters }: Props) {
    const { t } = useTranslations();
    const [pending, setPending] = useState<string | null>(null);

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

    async function handleExport(format: string) {
        setPending(format);

        try {
            const response = await fetch(href(format));

            if (
                response.headers
                    .get('content-type')
                    ?.includes('application/json')
            ) {
                const payload = (await response.json()) as { message: string };
                toast.info(payload.message);

                return;
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filenameFrom(response);
            link.click();
            URL.revokeObjectURL(url);
        } finally {
            setPending(null);
        }
    }

    return (
        <div className="flex items-center justify-end gap-2">
            {FORMATS.map(({ format, icon: Icon }) => (
                <Button
                    key={format}
                    variant="outline"
                    size="sm"
                    disabled={pending === format}
                    onClick={() => handleExport(format)}
                >
                    <Icon className="size-4" />
                    {t(`ui.dt.reports.${reportType}.export.${format}`)}
                </Button>
            ))}
        </div>
    );
}
