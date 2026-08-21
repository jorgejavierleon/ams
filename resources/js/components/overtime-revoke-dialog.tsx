import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslations } from '@/hooks/use-translations';
import { revoke as revokeOvertime } from '@/routes/workdays/overtime';

export type OvertimeRevokeTarget = {
    workday_id: number;
    employee: string | null;
    date: string;
} | null;

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    target: OvertimeRevokeTarget;
};

/**
 * Revoke a day's already-approved overtime (KOL-80) — shared by the Jornadas
 * index and the day detail page's merged history timeline, so both act
 * through the exact same form. The record is kept, not deleted; the reason
 * is required so the revocation is answerable to the employee later.
 */
export default function OvertimeRevokeDialog({
    open,
    onOpenChange,
    target,
}: Props) {
    const { t } = useTranslations();
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm({ reason: '' });

    useEffect(() => {
        if (open) {
            clearErrors();
            setData('reason', '');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, target?.workday_id]);

    function submit() {
        if (!target) {
            return;
        }

        post(revokeOvertime(target.workday_id).url, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('ui.workdays.show.overtime.revoke_dialog.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'ui.workdays.show.overtime.revoke_dialog.description',
                            {
                                employee: target?.employee ?? '',
                                date: target?.date ?? '',
                            },
                        )}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 py-2">
                    <FormField
                        label={t(
                            'ui.workdays.show.overtime.revoke_dialog.reason',
                        )}
                        htmlFor="overtime_revoke_reason"
                        required
                        error={errors.reason}
                    >
                        <textarea
                            id="overtime_revoke_reason"
                            rows={3}
                            value={data.reason}
                            onChange={(event) =>
                                setData('reason', event.target.value)
                            }
                            className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        />
                    </FormField>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        {t('ui.common.cancel')}
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={submit}
                        disabled={processing}
                    >
                        {t('ui.workdays.show.overtime.revoke_dialog.submit')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
