import { Head, Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import Heading from '@/components/heading';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslations } from '@/hooks/use-translations';
import { toneChip } from '@/lib/status-tone';
import { cn } from '@/lib/utils';
import { show } from '@/routes/my/documents';

type Chip = { value: string; label: string; tone: string };

type MyDocument = {
    id: number;
    title: string;
    type: string | null;
    status: Chip;
    published_at: string | null;
    my_signature: { status: string; label: string; tone: string } | null;
    awaiting_me: boolean;
};

type Props = {
    documents: MyDocument[];
};

export default function MyDocumentsIndex({ documents }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.documents.my.title')} />

            <div className="space-y-5 p-6">
                <Heading
                    title={t('ui.documents.my.title')}
                    description={t('ui.documents.my.description')}
                />

                {documents.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed py-16 text-center text-muted-foreground">
                        <FileText className="size-8" />
                        <p>{t('ui.documents.my.empty')}</p>
                    </div>
                ) : (
                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>
                                        {t('ui.documents.my.columns.title')}
                                    </TableHead>
                                    <TableHead>
                                        {t('ui.documents.my.columns.type')}
                                    </TableHead>
                                    <TableHead>
                                        {t('ui.documents.my.columns.status')}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.documents.my.columns.my_signature',
                                        )}
                                    </TableHead>
                                    <TableHead>
                                        {t(
                                            'ui.documents.my.columns.published_at',
                                        )}
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {documents.map((document) => (
                                    <TableRow key={document.id}>
                                        <TableCell>
                                            <Link
                                                href={show(document.id).url}
                                                className="flex items-center gap-2 font-medium hover:underline"
                                            >
                                                {document.title}
                                                {document.awaiting_me && (
                                                    <span className="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-400">
                                                        {t(
                                                            'ui.documents.my.awaiting_you',
                                                        )}
                                                    </span>
                                                )}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {document.type ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <span
                                                className={cn(
                                                    'inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium',
                                                    toneChip(
                                                        document.status.tone,
                                                    ),
                                                )}
                                            >
                                                {document.status.label}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            {document.my_signature ? (
                                                <span
                                                    className={cn(
                                                        'inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium',
                                                        toneChip(
                                                            document
                                                                .my_signature
                                                                .tone,
                                                        ),
                                                    )}
                                                >
                                                    {
                                                        document.my_signature
                                                            .label
                                                    }
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {document.published_at ?? '—'}
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
