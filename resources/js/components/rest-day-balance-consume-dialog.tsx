import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import { Combobox } from '@/components/combobox';
import type { ComboboxOption } from '@/components/combobox';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { consume } from '@/routes/overtime/rest-day-balances';

type FormData = {
    user_id: string;
    hours: string;
    consumed_on: string;
    note: string;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    employeeOptions: ComboboxOption[];
};

export default function RestDayBalanceConsumeDialog({
    open,
    onOpenChange,
    employeeOptions,
}: Props) {
    const { t } = useTranslations();
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm<FormData>({
            user_id: '',
            hours: '',
            consumed_on: new Date().toISOString().slice(0, 10),
            note: '',
        });

    useEffect(() => {
        if (open) {
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    function submit(event: FormEvent) {
        event.preventDefault();

        post(consume().url, {
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
                <form onSubmit={submit} className="grid gap-6">
                    <DialogHeader>
                        <DialogTitle>
                            {t(
                                'ui.overtime.rest_day_balances.consume_dialog.title',
                            )}
                        </DialogTitle>
                    </DialogHeader>

                    <FormField
                        label={t(
                            'ui.overtime.rest_day_balances.consume_dialog.employee',
                        )}
                        htmlFor="user_id"
                        required
                        error={errors.user_id}
                    >
                        <Combobox
                            id="user_id"
                            options={employeeOptions}
                            value={data.user_id}
                            onChange={(value) => setData('user_id', value)}
                            placeholder={t(
                                'ui.overtime.rest_day_balances.consume_dialog.employee_placeholder',
                            )}
                            searchPlaceholder={t(
                                'ui.overtime.rest_day_balances.consume_dialog.employee_search',
                            )}
                            emptyLabel={t(
                                'ui.overtime.rest_day_balances.consume_dialog.employee_empty',
                            )}
                            modal
                        />
                    </FormField>

                    <FormField
                        label={t(
                            'ui.overtime.rest_day_balances.consume_dialog.hours',
                        )}
                        htmlFor="hours"
                        required
                        error={errors.hours}
                    >
                        <Input
                            id="hours"
                            type="time"
                            value={data.hours}
                            onChange={(e) => setData('hours', e.target.value)}
                        />
                    </FormField>

                    <FormField
                        label={t(
                            'ui.overtime.rest_day_balances.consume_dialog.consumed_on',
                        )}
                        htmlFor="consumed_on"
                        required
                        error={errors.consumed_on}
                    >
                        <Input
                            id="consumed_on"
                            type="date"
                            value={data.consumed_on}
                            onChange={(e) =>
                                setData('consumed_on', e.target.value)
                            }
                        />
                    </FormField>

                    <FormField
                        label={t(
                            'ui.overtime.rest_day_balances.consume_dialog.note',
                        )}
                        htmlFor="note"
                        error={errors.note}
                    >
                        <textarea
                            id="note"
                            rows={3}
                            value={data.note}
                            placeholder={t(
                                'ui.overtime.rest_day_balances.consume_dialog.note_placeholder',
                            )}
                            onChange={(e) => setData('note', e.target.value)}
                            className="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                        />
                    </FormField>

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
                                'ui.overtime.rest_day_balances.consume_dialog.submit',
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
