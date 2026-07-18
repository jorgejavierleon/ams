import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    Download,
    PenLine,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslations } from '@/hooks/use-translations';
import { toneChip } from '@/lib/status-tone';
import { cn } from '@/lib/utils';
import { download, index, reject, sendCode, sign } from '@/routes/my/documents';

type Chip = { value: string; label: string; tone: string };

type Props = {
    document: {
        id: number;
        title: string;
        type: string | null;
        status: Chip;
        body: string;
        published_at: string | null;
        signed_at: string | null;
        has_signed_pdf: boolean;
    };
    my_signature: {
        status: Chip;
        signed_at: string | null;
        can_sign: boolean;
    } | null;
};

export default function MyDocumentShow({ document, my_signature }: Props) {
    const { t } = useTranslations();
    const [codeSent, setCodeSent] = useState(false);
    const [rejecting, setRejecting] = useState(false);

    const signForm = useForm({ code: '' });
    const rejectForm = useForm({ reason: '' });

    function requestCode() {
        router.post(
            sendCode(document.id).url,
            {},
            {
                preserveScroll: true,
                onSuccess: () => setCodeSent(true),
            },
        );
    }

    function submitSign() {
        signForm.post(sign(document.id).url, { preserveScroll: true });
    }

    function submitReject() {
        rejectForm.post(reject(document.id).url, {
            preserveScroll: true,
            onFinish: () => setRejecting(false),
        });
    }

    const status = my_signature?.status.value;

    return (
        <>
            <Head title={document.title} />

            <div className="space-y-5 p-6">
                <Button variant="ghost" size="sm" asChild className="-ml-2">
                    <Link href={index().url}>
                        <ArrowLeft className="size-4" />
                        {t('ui.documents.my.show.back')}
                    </Link>
                </Button>

                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            {t('ui.documents.my.show.eyebrow')}
                        </p>
                        <h1 className="text-2xl font-semibold">
                            {document.title}
                        </h1>
                        <span
                            className={cn(
                                'mt-2 inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium',
                                toneChip(document.status.tone),
                            )}
                        >
                            {document.status.label}
                        </span>
                    </div>

                    {document.has_signed_pdf && (
                        <Button variant="outline" asChild>
                            <a href={download(document.id).url}>
                                <Download className="size-4" />
                                {t('ui.documents.my.show.download_signed')}
                            </a>
                        </Button>
                    )}
                </div>

                <div className="grid gap-5 lg:grid-cols-[1fr_20rem]">
                    <article className="rounded-lg border bg-card p-6">
                        <h2 className="mb-3 text-sm font-medium text-muted-foreground">
                            {t('ui.documents.my.show.body')}
                        </h2>
                        <div
                            className="prose prose-sm dark:prose-invert max-w-none"
                            dangerouslySetInnerHTML={{ __html: document.body }}
                        />
                    </article>

                    {my_signature && (
                        <aside className="h-fit space-y-4 rounded-lg border bg-card p-5">
                            <h2 className="flex items-center gap-2 text-sm font-medium">
                                <PenLine className="size-4" />
                                {t('ui.documents.my.show.sign_panel')}
                            </h2>

                            {status === 'signed' && (
                                <p className="flex items-center gap-2 text-sm text-emerald-600 dark:text-emerald-400">
                                    <CheckCircle2 className="size-4" />
                                    {t('ui.documents.my.show.already_signed')}
                                </p>
                            )}

                            {status === 'rejected' && (
                                <p className="flex items-center gap-2 text-sm text-red-600 dark:text-red-400">
                                    <XCircle className="size-4" />
                                    {t('ui.documents.my.show.already_rejected')}
                                </p>
                            )}

                            {status === 'cancelled' && (
                                <p className="text-sm text-muted-foreground">
                                    {my_signature.status.label}
                                </p>
                            )}

                            {status === 'pending' &&
                                (my_signature.can_sign ? (
                                    <div className="space-y-3">
                                        {!codeSent ? (
                                            <Button
                                                className="w-full"
                                                onClick={requestCode}
                                            >
                                                {t(
                                                    'ui.documents.my.show.request_code',
                                                )}
                                            </Button>
                                        ) : (
                                            <>
                                                <div className="space-y-1.5">
                                                    <Label htmlFor="code">
                                                        {t(
                                                            'ui.documents.my.show.code_label',
                                                        )}
                                                    </Label>
                                                    <Input
                                                        id="code"
                                                        inputMode="numeric"
                                                        autoComplete="one-time-code"
                                                        value={
                                                            signForm.data.code
                                                        }
                                                        onChange={(e) =>
                                                            signForm.setData(
                                                                'code',
                                                                e.target.value,
                                                            )
                                                        }
                                                    />
                                                    <p className="text-xs text-muted-foreground">
                                                        {t(
                                                            'ui.documents.my.show.code_hint',
                                                        )}
                                                    </p>
                                                    {signForm.errors.code && (
                                                        <p className="text-xs text-red-600 dark:text-red-400">
                                                            {
                                                                signForm.errors
                                                                    .code
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                <Button
                                                    className="w-full"
                                                    onClick={submitSign}
                                                    disabled={
                                                        signForm.processing ||
                                                        signForm.data.code ===
                                                            ''
                                                    }
                                                >
                                                    {t(
                                                        'ui.documents.my.show.sign',
                                                    )}
                                                </Button>
                                                <button
                                                    type="button"
                                                    className="w-full text-xs text-muted-foreground hover:underline"
                                                    onClick={requestCode}
                                                >
                                                    {t(
                                                        'ui.documents.my.show.resend_code',
                                                    )}
                                                </button>
                                            </>
                                        )}

                                        <Button
                                            variant="outline"
                                            className="w-full text-red-600 hover:text-red-700 dark:text-red-400"
                                            onClick={() => setRejecting(true)}
                                        >
                                            {t('ui.documents.my.show.reject')}
                                        </Button>
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            'ui.documents.my.show.not_your_turn',
                                        )}
                                    </p>
                                ))}

                            {status === 'signed' &&
                                document.status.value ===
                                    'pending_signature' && (
                                    <p className="text-sm text-muted-foreground">
                                        {t(
                                            'ui.documents.my.show.waiting_others',
                                        )}
                                    </p>
                                )}
                        </aside>
                    )}
                </div>
            </div>

            <ConfirmDialog
                open={rejecting}
                onOpenChange={setRejecting}
                title={t('ui.documents.my.show.reject_confirm_title')}
                description={t(
                    'ui.documents.my.show.reject_confirm_description',
                )}
                confirmLabel={t('ui.documents.my.show.reject')}
                variant="destructive"
                processing={rejectForm.processing}
                onConfirm={submitReject}
            />
        </>
    );
}
