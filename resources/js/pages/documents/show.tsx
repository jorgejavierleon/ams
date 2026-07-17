import { Deferred, Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeft,
    Download,
    FileClock,
    FileText,
    PenLine,
    Send,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { ActivityTimeline } from '@/components/activity-timeline';
import type { ActivityEntry } from '@/components/activity-timeline';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DocumentSignaturesPanel } from '@/components/document-signatures-panel';
import type { DocumentSignature } from '@/components/document-signatures-panel';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useTranslations } from '@/hooks/use-translations';
import { toneChip } from '@/lib/status-tone';
import { cn } from '@/lib/utils';
import { download, edit, index, publish } from '@/routes/documents';

type StatusChip = {
    value: string;
    label: string;
    variant: 'default' | 'secondary' | 'destructive' | 'outline';
    tone: string;
};

type Props = {
    document: {
        id: number;
        title: string;
        type: string | null;
        employee: { id: number | null; name: string | null };
        status: StatusChip;
        legal_rep_signatories: number;
        ordered_signing: boolean;
        body: string;
        published_at: string | null;
        signed_at: string | null;
        can_publish: boolean;
    };
    signatures: DocumentSignature[];
    activities?: ActivityEntry[];
};

export default function DocumentShow({
    document,
    signatures,
    activities,
}: Props) {
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

    const signedCount = signatures.filter(
        (signature) => signature.status.value === 'signed',
    ).length;
    const allSigned =
        signatures.length > 0 && signedCount === signatures.length;

    return (
        <>
            <Head title={document.title} />

            <div className="space-y-5 p-6">
                <Button
                    variant="ghost"
                    size="sm"
                    asChild
                    className="-ml-2 text-muted-foreground"
                >
                    <Link href={index()}>
                        <ArrowLeft className="size-4" />
                        {t('ui.documents.show.back')}
                    </Link>
                </Button>

                {/* Header band */}
                <header className="flex flex-wrap items-start justify-between gap-4 border-b pb-5">
                    <div className="flex items-center gap-4">
                        <div className="grid size-13 place-items-center rounded-full border bg-muted text-muted-foreground">
                            <FileText className="size-6" />
                        </div>
                        <div>
                            <div className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {t('ui.documents.show.eyebrow')}
                            </div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {document.title}
                            </h1>
                            <div className="mt-0.5 text-sm text-muted-foreground">
                                {document.type && `${document.type} · `}
                                {document.employee.name ?? '—'}
                            </div>
                        </div>
                    </div>
                    <div className="flex flex-col items-end gap-2.5">
                        <span
                            className={cn(
                                'inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold',
                                toneChip(document.status.tone),
                            )}
                        >
                            <span className="size-1.5 rounded-full bg-current" />
                            {document.status.label}
                        </span>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <a href={download(document.id).url}>
                                    <Download className="size-4" />
                                    {t('ui.documents.actions.download')}
                                </a>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <Link href={edit(document.id)}>
                                    <PenLine className="size-4" />
                                    {t('ui.documents.actions.edit')}
                                </Link>
                            </Button>
                            {document.can_publish && (
                                <Button
                                    size="sm"
                                    onClick={() => setPublishing(true)}
                                >
                                    <Send className="size-4" />
                                    {t('ui.documents.actions.publish')}
                                </Button>
                            )}
                        </div>
                    </div>
                </header>

                {/* KPI tiles */}
                <div
                    className={cn(
                        'grid grid-cols-2 gap-3',
                        signatures.length > 0
                            ? 'lg:grid-cols-4'
                            : 'lg:grid-cols-3',
                    )}
                >
                    <StatTile
                        label={t('ui.documents.columns.type')}
                        value={document.type ?? '—'}
                    />
                    <StatTile
                        label={t('ui.documents.columns.published_at')}
                        value={document.published_at ?? '—'}
                    />
                    <StatTile
                        label={t('ui.documents.columns.signed_at')}
                        value={document.signed_at ?? '—'}
                        tone={document.signed_at ? 'ok' : 'plain'}
                    />
                    {signatures.length > 0 && (
                        <StatTile
                            label={t('ui.documents.show.signatures')}
                            value={`${signedCount} / ${signatures.length}`}
                            sub={t('ui.documents.signatures.progress')}
                            tone={allSigned ? 'ok' : 'plain'}
                        />
                    )}
                </div>

                {/* Body + side rail */}
                <div className="grid items-start gap-4 lg:grid-cols-[1fr_360px]">
                    <Section
                        title={t('ui.documents.show.body')}
                        hint={t('ui.documents.show.body_hint')}
                    >
                        {document.body ? (
                            <div
                                className="tiptap"
                                // The body is trusted HTML authored by an admin
                                // in the rich editor and resolved server-side
                                // for preview.
                                dangerouslySetInnerHTML={{
                                    __html: document.body,
                                }}
                            />
                        ) : (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                {t('ui.documents.show.body_empty')}
                            </p>
                        )}
                    </Section>

                    <div className="flex flex-col gap-4">
                        <Section title={t('ui.documents.show.details')}>
                            <dl className="grid gap-3">
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
                            </dl>
                        </Section>

                        <Section
                            title={t('ui.documents.show.signatures')}
                            icon={<PenLine className="size-4" />}
                            count={
                                signatures.length > 0
                                    ? signatures.length
                                    : undefined
                            }
                        >
                            <DocumentSignaturesPanel signatures={signatures} />
                        </Section>

                        <Section
                            title={t('ui.documents.show.activity')}
                            icon={<FileClock className="size-4" />}
                        >
                            <Deferred
                                data="activities"
                                fallback={<ActivityTimelineSkeleton />}
                            >
                                <ActivityTimeline
                                    activities={activities ?? []}
                                />
                            </Deferred>
                        </Section>
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

/** A titled section card matching the workday detail sections. */
function Section({
    title,
    hint,
    icon,
    count,
    children,
}: {
    title: string;
    hint?: string;
    icon?: ReactNode;
    count?: number;
    children: ReactNode;
}) {
    return (
        <section className="rounded-xl border bg-card shadow-xs">
            <div className="flex items-center justify-between gap-3 border-b px-5 py-3.5">
                <h2 className="flex items-center gap-2 text-[13px] font-semibold">
                    {icon}
                    {title}
                </h2>
                {count !== undefined && (
                    <span className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase tabular-nums">
                        {count}
                    </span>
                )}
                {hint && (
                    <span className="text-xs text-muted-foreground">
                        {hint}
                    </span>
                )}
            </div>
            <div className="p-5">{children}</div>
        </section>
    );
}

/** A key metric tile matching the workday KPI tiles. */
function StatTile({
    label,
    value,
    sub,
    tone = 'plain',
}: {
    label: string;
    value: ReactNode;
    sub?: string;
    tone?: 'ok' | 'plain';
}) {
    return (
        <div className="relative overflow-hidden rounded-xl border bg-card p-4 shadow-xs">
            {tone === 'ok' && (
                <span className="absolute inset-y-0 left-0 w-[3px] bg-emerald-500" />
            )}
            <div className="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase">
                {label}
            </div>
            <div
                className={cn(
                    'mt-1.5 truncate text-lg font-semibold tracking-tight',
                    tone === 'ok' && 'text-emerald-600 dark:text-emerald-400',
                )}
            >
                {value}
            </div>
            {sub && (
                <div className="mt-1 text-xs text-muted-foreground">{sub}</div>
            )}
        </div>
    );
}

/** Pulsing placeholder shown while the deferred activity prop loads. */
function ActivityTimelineSkeleton() {
    return (
        <div className="space-y-4">
            {[0, 1, 2].map((row) => (
                <div key={row} className="flex gap-3">
                    <Skeleton className="size-7 shrink-0 rounded-full" />
                    <div className="flex-1 space-y-2 py-0.5">
                        <Skeleton className="h-3.5 w-2/3" />
                        <Skeleton className="h-3 w-1/2" />
                    </div>
                </div>
            ))}
        </div>
    );
}

function DetailRow({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-right text-sm font-medium">{value}</dd>
        </div>
    );
}
