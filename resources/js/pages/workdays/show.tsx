import { Head, useForm } from '@inertiajs/react';
import { PencilLine } from 'lucide-react';
import { useState } from 'react';
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
import WorkdayDetail, { hm } from '@/components/workday-detail';
import type {
    Modification,
    WorkdayDetailData,
} from '@/components/workday-detail';
import { useTranslations } from '@/hooks/use-translations';
import { show as showEmployee } from '@/routes/employees';
import { index, modify } from '@/routes/workdays';
import { approve, decline } from '@/routes/workdays/modifications';

type Option = { value: string; label: string };

type Props = {
    workday: WorkdayDetailData;
    modifications: Modification[];
    reasonOptions: Option[];
};

export default function WorkdayShow({
    workday,
    modifications,
    reasonOptions,
}: Props) {
    const { t } = useTranslations();

    const [modifyOpen, setModifyOpen] = useState(false);

    const modifyForm = useForm({
        mark_in: hm(workday.mark_in.time) ?? '',
        mark_out: hm(workday.mark_out.time) ?? '',
        reason: reasonOptions[0]?.value ?? '',
        notes: '',
    });

    function openModify() {
        modifyForm.clearErrors();
        modifyForm.setData({
            mark_in: hm(workday.mark_in.time) ?? '',
            mark_out: hm(workday.mark_out.time) ?? '',
            reason: reasonOptions[0]?.value ?? '',
            notes: '',
        });
        setModifyOpen(true);
    }

    function submitModify() {
        modifyForm.post(modify(workday.id).url, {
            preserveScroll: true,
            onSuccess: () => setModifyOpen(false),
        });
    }

    return (
        <>
            <Head title={workday.date_label} />

            <WorkdayDetail
                workday={workday}
                modifications={modifications}
                backHref={index().url}
                backLabel={t('ui.workdays.show.back')}
                employeeHref={showEmployee(workday.employee.id).url}
                reviewUrl={(action, modificationId) =>
                    (action === 'approve' ? approve : decline)({
                        workday: workday.id,
                        markModification: modificationId,
                    }).url
                }
                onModifyMark={openModify}
                headerAction={
                    <Button onClick={openModify}>
                        <PencilLine className="size-4" />
                        {t('ui.workdays.actions.modify')}
                    </Button>
                }
            />

            {/* Modify marks */}
            <Dialog
                open={modifyOpen}
                onOpenChange={(open) => !open && setModifyOpen(false)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('ui.workdays.modify.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('ui.workdays.modify.description', {
                                employee: workday.employee.name ?? '—',
                                date: workday.date,
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 py-2">
                        <div className="grid grid-cols-2 gap-4">
                            <FormField
                                label={t('ui.workdays.modify.mark_in')}
                                htmlFor="modify_mark_in"
                                error={modifyForm.errors.mark_in}
                            >
                                <Input
                                    id="modify_mark_in"
                                    type="time"
                                    value={modifyForm.data.mark_in}
                                    onChange={(event) =>
                                        modifyForm.setData(
                                            'mark_in',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                label={t('ui.workdays.modify.mark_out')}
                                htmlFor="modify_mark_out"
                                error={modifyForm.errors.mark_out}
                            >
                                <Input
                                    id="modify_mark_out"
                                    type="time"
                                    value={modifyForm.data.mark_out}
                                    onChange={(event) =>
                                        modifyForm.setData(
                                            'mark_out',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </div>

                        <FormField
                            label={t('ui.workdays.modify.reason')}
                            htmlFor="modify_reason"
                            required
                            error={modifyForm.errors.reason}
                        >
                            <Select
                                value={modifyForm.data.reason}
                                onValueChange={(value) =>
                                    modifyForm.setData('reason', value)
                                }
                            >
                                <SelectTrigger id="modify_reason">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {reasonOptions.map((option) => (
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

                        <FormField
                            label={t('ui.workdays.modify.notes')}
                            htmlFor="modify_notes"
                            error={modifyForm.errors.notes}
                        >
                            <textarea
                                id="modify_notes"
                                rows={3}
                                value={modifyForm.data.notes}
                                onChange={(event) =>
                                    modifyForm.setData(
                                        'notes',
                                        event.target.value,
                                    )
                                }
                                className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </FormField>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setModifyOpen(false)}
                        >
                            {t('ui.common.cancel')}
                        </Button>
                        <Button
                            onClick={submitModify}
                            disabled={modifyForm.processing}
                        >
                            {t('ui.workdays.modify.submit')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
