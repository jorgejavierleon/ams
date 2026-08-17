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
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import {
    decimalHoursToTime,
    timeToDecimalHours,
} from '@/lib/overtime-duration';
import { approve as approveOvertime } from '@/routes/workdays/overtime';

export type OvertimeApproveTarget = {
    workday_id: number;
    employee: string | null;
    date: string;
    calculated_hours: string | null;
    authorized_hours: string | null;
    compensation_eligible: boolean;
} | null;

type Option = { value: string; label: string };

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    target: OvertimeApproveTarget;
    compensationTypeOptions: Option[];
};

/**
 * Approve a day's overtime — shared by the Jornadas index (quick action) and
 * the day detail page's merged history timeline (KOL-71), so both act
 * through the exact same form instead of two divergent copies.
 */
export default function OvertimeApproveDialog({
    open,
    onOpenChange,
    target,
    compensationTypeOptions,
}: Props) {
    const { t } = useTranslations();
    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
        clearErrors,
        transform,
    } = useForm({
            authorized_hours: '',
            compensation_type: 'payment',
            reason: '',
        });

    useEffect(() => {
        if (open && target) {
            clearErrors();
            setData({
                authorized_hours: timeToDecimalHours(
                    target.authorized_hours ?? target.calculated_hours,
                ),
                compensation_type: 'payment',
                reason: '',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, target?.workday_id]);

    function submit() {
        if (!target) {
            return;
        }

        transform((formData) => ({
            ...formData,
            authorized_hours: decimalHoursToTime(formData.authorized_hours),
        }));
        post(approveOvertime(target.workday_id).url, {
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
                        {t('ui.workdays.show.overtime.approve_dialog.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'ui.workdays.show.overtime.approve_dialog.description',
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
                            'ui.workdays.show.overtime.approve_dialog.authorized_hours',
                        )}
                        htmlFor="overtime_authorized_hours"
                        error={errors.authorized_hours}
                    >
                        <Input
                            id="overtime_authorized_hours"
                            type="number"
                            min="0"
                            step="0.25"
                            className="w-28"
                            value={data.authorized_hours}
                            onChange={(event) =>
                                setData('authorized_hours', event.target.value)
                            }
                        />
                    </FormField>

                    {target?.compensation_eligible && (
                        <FormField
                            label={t(
                                'ui.workdays.show.overtime.approve_dialog.compensation_type',
                            )}
                            htmlFor="overtime_compensation_type"
                            hint={t(
                                'ui.workdays.show.overtime.approve_dialog.compensation_type_hint',
                                { employee: target?.employee ?? '' },
                            )}
                            error={errors.compensation_type}
                        >
                            <Select
                                value={data.compensation_type}
                                onValueChange={(value) =>
                                    setData('compensation_type', value)
                                }
                            >
                                <SelectTrigger id="overtime_compensation_type">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {compensationTypeOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </FormField>
                    )}

                    <FormField
                        label={t(
                            'ui.workdays.show.overtime.approve_dialog.reason',
                        )}
                        htmlFor="overtime_approve_reason"
                        hint={t(
                            'ui.workdays.show.overtime.approve_dialog.reason_hint',
                        )}
                        error={errors.reason}
                    >
                        <textarea
                            id="overtime_approve_reason"
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
                    <Button onClick={submit} disabled={processing}>
                        {t('ui.workdays.show.overtime.approve_dialog.submit')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
