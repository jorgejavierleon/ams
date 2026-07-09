import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { store, update } from '@/routes/holidays';

export type HolidayFormTarget = {
    id: number;
    name: string;
    date: string;
    mandatory: boolean;
} | null;

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** When set, the dialog edits this holiday; otherwise it creates one. */
    holiday?: HolidayFormTarget;
};

type HolidayForm = {
    name: string;
    date: string;
    mandatory: boolean;
};

export default function HolidayFormDialog({
    open,
    onOpenChange,
    holiday,
}: Props) {
    const { t } = useTranslations();
    const isEdit = Boolean(holiday);
    const {
        data,
        setData,
        post,
        patch,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm<HolidayForm>({ name: '', date: '', mandatory: true });

    // Sync the fields with the target whenever the dialog is (re)opened.
    useEffect(() => {
        if (open) {
            setData({
                name: holiday?.name ?? '',
                date: holiday?.date ?? '',
                mandatory: holiday?.mandatory ?? true,
            });
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, holiday?.id]);

    function submit(event: FormEvent) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        };

        if (isEdit && holiday) {
            patch(update(holiday.id).url, options);
        } else {
            post(store().url, options);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-6">
                    <DialogHeader>
                        <DialogTitle>
                            {t(
                                isEdit
                                    ? 'ui.holidays.edit_dialog.title'
                                    : 'ui.holidays.create_dialog.title',
                            )}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="name">
                            {t('ui.holidays.form.name')}
                        </Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder={t('ui.holidays.form.name_placeholder')}
                            required
                            autoFocus
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="date">
                            {t('ui.holidays.form.date')}
                        </Label>
                        <Input
                            id="date"
                            type="date"
                            value={data.date}
                            onChange={(e) => setData('date', e.target.value)}
                            // The date identifies the holiday and is immutable once created.
                            disabled={isEdit}
                            required
                        />
                        <InputError message={errors.date} />
                    </div>

                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="mandatory"
                            checked={data.mandatory}
                            onCheckedChange={(checked) =>
                                setData('mandatory', checked === true)
                            }
                        />
                        <div className="grid gap-1 leading-none">
                            <Label htmlFor="mandatory">
                                {t('ui.holidays.form.mandatory')}
                            </Label>
                            <p className="text-sm text-muted-foreground">
                                {t('ui.holidays.form.mandatory_hint')}
                            </p>
                            <InputError message={errors.mandatory} />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            {t('ui.common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {t(
                                isEdit
                                    ? 'ui.holidays.edit_dialog.submit'
                                    : 'ui.holidays.create_dialog.submit',
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
