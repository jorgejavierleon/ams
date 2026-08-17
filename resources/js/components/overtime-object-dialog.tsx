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
import { object as objectOvertime } from '@/routes/workdays/overtime';

export type OvertimeObjectTarget = {
    workday_id: number;
    employee: string | null;
    date: string;
} | null;

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    target: OvertimeObjectTarget;
};

/**
 * Object to a day's overtime — shared by the Jornadas index (quick action)
 * and the day detail page's merged history timeline (KOL-71).
 */
export default function OvertimeObjectDialog({
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

        post(objectOvertime(target.workday_id).url, {
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
                        {t('ui.workdays.show.overtime.object_dialog.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'ui.workdays.show.overtime.object_dialog.description',
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
                            'ui.workdays.show.overtime.object_dialog.reason',
                        )}
                        htmlFor="overtime_object_reason"
                        required
                        error={errors.reason}
                    >
                        <textarea
                            id="overtime_object_reason"
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
                        {t('ui.workdays.show.overtime.object_dialog.submit')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
