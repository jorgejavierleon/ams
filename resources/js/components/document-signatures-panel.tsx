import { router } from '@inertiajs/react';
import { Send } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/hooks/use-translations';
import { toneChip, toneDot } from '@/lib/status-tone';
import { cn } from '@/lib/utils';
import { resend } from '@/routes/document-signatures';

type StatusChip = {
    value: string;
    label: string;
    tone: string;
};

export type DocumentSignature = {
    id: number;
    name: string | null;
    type: string;
    status: StatusChip;
    order: number | null;
    signed_at: string | null;
    can_resend: boolean;
};

/**
 * Read-only signing-progress timeline embedded in the document detail page.
 * Each signatory is a node on a vertical track — coloured by status tone and,
 * for ordered documents, numbered by signing sequence. Pending signatures
 * expose a "Resend" action that re-dispatches the signing invitation; no manual
 * status override is possible.
 */
export function DocumentSignaturesPanel({
    signatures,
}: {
    signatures: DocumentSignature[];
}) {
    const { t } = useTranslations();

    if (signatures.length === 0) {
        return (
            <p className="py-8 text-center text-sm text-muted-foreground">
                {t('ui.documents.signatures.empty')}
            </p>
        );
    }

    return (
        <div className="relative">
            <div className="absolute top-2 bottom-2 left-[9px] w-px bg-border" />
            {signatures.map((signature) => (
                <SignatureRow key={signature.id} signature={signature} />
            ))}
        </div>
    );
}

function SignatureRow({ signature }: { signature: DocumentSignature }) {
    const { t } = useTranslations();
    const [resending, setResending] = useState(false);

    function handleResend() {
        router.post(
            resend(signature.id).url,
            {},
            {
                preserveScroll: true,
                onStart: () => setResending(true),
                onFinish: () => setResending(false),
            },
        );
    }

    return (
        <div className="relative pb-6 pl-8 last:pb-0">
            <span
                className={cn(
                    'absolute top-1 left-[2px] size-3.5 rounded-full ring-4 ring-card',
                    toneDot(signature.status.tone),
                )}
            />

            <div className="flex flex-wrap items-center gap-2">
                {signature.order !== null && (
                    <span className="inline-flex size-5 items-center justify-center rounded-full border text-[10px] font-bold text-muted-foreground tabular-nums">
                        {signature.order}
                    </span>
                )}
                <span className="text-[13px] font-semibold">
                    {signature.name ?? '—'}
                </span>
                <span
                    className={cn(
                        'ml-auto rounded-full border px-2 py-0.5 text-[10px] font-bold tracking-wide uppercase',
                        toneChip(signature.status.tone),
                    )}
                >
                    {signature.status.label}
                </span>
            </div>

            <div className="mt-1 text-[12.5px] text-muted-foreground">
                {signature.type}
                {signature.signed_at && (
                    <>
                        {' · '}
                        {t('ui.documents.signatures.signed_at', {
                            date: signature.signed_at,
                        })}
                    </>
                )}
            </div>

            {signature.can_resend && (
                <Button
                    variant="ghost"
                    size="sm"
                    className="mt-2 -ml-2 h-7 text-muted-foreground"
                    onClick={handleResend}
                    disabled={resending}
                >
                    <Send className="size-3.5" />
                    {t('ui.documents.signatures.resend.action')}
                </Button>
            )}
        </div>
    );
}
