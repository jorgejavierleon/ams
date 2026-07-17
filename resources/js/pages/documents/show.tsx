import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Download, FileClock, PenLine, Send } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';
import { download, edit, index, publish } from '@/routes/documents';

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
        employee: { id: number | null; name: string | null };
        status: StatusBadge;
        legal_rep_signatories: number;
        ordered_signing: boolean;
        body: string;
        published_at: string | null;
        signed_at: string | null;
        can_publish: boolean;
    };
    signatures: unknown[];
    activities: unknown[];
};

export default function DocumentShow({ document }: Props) {
    const { t } = useTranslations();
    const [publishing, setPublishing] = useState(false);

    function confirmPublish() {
        router.post(
            publish(document.id).url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setPublishing(false),
            },
        );
    }

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
                            {t('ui.documents.show.back')}
                        </Link>
                        <Heading
                            title={document.title}
                            description={document.type ?? undefined}
                        />
                        <Badge variant={document.status.variant}>
                            {document.status.label}
                        </Badge>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <a href={download(document.id).url}>
                                <Download className="size-4" />
                                {t('ui.documents.actions.download')}
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={edit(document.id)}>
                                <PenLine className="size-4" />
                                {t('ui.documents.actions.edit')}
                            </Link>
                        </Button>
                        {document.can_publish && (
                            <Button onClick={() => setPublishing(true)}>
                                <Send className="size-4" />
                                {t('ui.documents.actions.publish')}
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {t('ui.documents.show.body')}
                                </CardTitle>
                                <CardDescription>
                                    {t('ui.documents.show.body_hint')}
                                </CardDescription>
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
                                        {t('ui.documents.show.body_empty')}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {t('ui.documents.show.details')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-3 text-sm">
                                <DetailRow
                                    label={t('ui.documents.show.employee')}
                                    value={document.employee.name ?? '—'}
                                />
                                <DetailRow
                                    label={t(
                                        'ui.documents.show.legal_rep_signatories',
                                    )}
                                    value={String(
                                        document.legal_rep_signatories,
                                    )}
                                />
                                <DetailRow
                                    label={t(
                                        'ui.documents.show.ordered_signing',
                                    )}
                                    value={
                                        document.ordered_signing
                                            ? t('ui.common.yes')
                                            : t('ui.common.no')
                                    }
                                />
                                <DetailRow
                                    label={t(
                                        'ui.documents.columns.published_at',
                                    )}
                                    value={document.published_at ?? '—'}
                                />
                                <DetailRow
                                    label={t('ui.documents.columns.signed_at')}
                                    value={document.signed_at ?? '—'}
                                />
                            </CardContent>
                        </Card>

                        {/* Signature status panel — built by #35. */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <PenLine className="size-4" />
                                    {t('ui.documents.show.signatures')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    {t('ui.documents.show.signatures_soon')}
                                </p>
                            </CardContent>
                        </Card>

                        {/* Activity timeline — built by #36. */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileClock className="size-4" />
                                    {t('ui.documents.show.activity')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    {t('ui.documents.show.activity_soon')}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <ConfirmDialog
                open={publishing}
                onOpenChange={setPublishing}
                title={t('ui.documents.publish_dialog.title')}
                description={t('ui.documents.publish_dialog.description')}
                confirmLabel={t('ui.documents.publish_dialog.confirm')}
                variant="default"
                onConfirm={confirmPublish}
            />
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
