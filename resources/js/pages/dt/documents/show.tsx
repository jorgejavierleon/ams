import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Download } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';
import { download, index } from '@/routes/dt/documents';

type StatusBadge = {
    value: string;
    label: string;
    variant: 'default' | 'secondary' | 'destructive' | 'outline';
};

type Props = {
    document: {
        id: number;
        title: string;
        type: string | null;
        employee: string | null;
        status: StatusBadge;
        body: string;
        published_at: string | null;
        signed_at: string | null;
    };
};

export default function DocumentShow({ document }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={document.title} />

            <div className="space-y-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-2">
                        <Link
                            href={index()}
                            className="inline-flex items-center gap-1 text-sm text-muted-foreground underline-offset-4 hover:underline"
                        >
                            <ArrowLeft className="size-4" />
                            {t('ui.dt.documents.show.back')}
                        </Link>
                        <Heading
                            title={document.title}
                            description={document.type ?? undefined}
                        />
                        <Badge variant={document.status.variant}>
                            {document.status.label}
                        </Badge>
                    </div>

                    <Button variant="outline" asChild>
                        <a href={download(document.id).url}>
                            <Download className="size-4" />
                            {t('ui.dt.documents.show.download')}
                        </a>
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {t('ui.dt.documents.show.body')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {document.body ? (
                                    <div
                                        className="tiptap"
                                        // The body is trusted HTML authored by an
                                        // admin in the rich editor and resolved
                                        // server-side for preview.
                                        dangerouslySetInnerHTML={{
                                            __html: document.body,
                                        }}
                                    />
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {t('ui.dt.documents.show.body_empty')}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {t('ui.dt.documents.show.details')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3 text-sm">
                                <DetailRow
                                    label={t(
                                        'ui.dt.documents.columns.employee',
                                    )}
                                    value={document.employee ?? '—'}
                                />
                                <DetailRow
                                    label={t('ui.dt.documents.columns.type')}
                                    value={document.type ?? '—'}
                                />
                                <DetailRow
                                    label={t('ui.dt.documents.columns.status')}
                                    value={document.status.label}
                                />
                                <DetailRow
                                    label={t(
                                        'ui.dt.documents.columns.published_at',
                                    )}
                                    value={document.published_at ?? '—'}
                                />
                                <DetailRow
                                    label={t(
                                        'ui.dt.documents.columns.signed_at',
                                    )}
                                    value={document.signed_at ?? '—'}
                                />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

function DetailRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value}</span>
        </div>
    );
}
