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
import { store, update } from '@/routes/overtime/pacts';

export type OvertimePactFormTarget = {
    id: number;
    user_id: number;
    start_date: string;
    end_date: string;
} | null;

type FormData = { user_id: string; start_date: string; end_date: string };

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** When set, the dialog edits this pacto; otherwise it creates one. */
    pact?: OvertimePactFormTarget;
    /**
     * The employee combobox, for the standalone pactos list where any
     * employee can be picked. Omit it and pass `employeeId` instead when the
     * dialog is already scoped to one employee (e.g. embedded in their
     * profile), and the field is hidden entirely.
     */
    employeeOptions?: ComboboxOption[];
    employeeId?: number;
};

export default function OvertimePactFormDialog({
    open,
    onOpenChange,
    pact,
    employeeOptions,
    employeeId,
}: Props) {
    const { t } = useTranslations();
    const isEdit = Boolean(pact);
    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<FormData>({ user_id: '', start_date: '', end_date: '' });

    // Sync the fields with the target whenever the dialog is (re)opened.
    useEffect(() => {
        if (open) {
            setData({
                user_id: pact
                    ? String(pact.user_id)
                    : employeeId
                      ? String(employeeId)
                      : '',
                start_date: pact?.start_date ?? '',
                end_date: pact?.end_date ?? '',
            });
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, pact?.id]);

    function submit(event: FormEvent) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        };

        if (isEdit && pact) {
            put(update(pact.id).url, options);
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
                                    ? 'ui.overtime.pacts.edit_dialog.title'
                                    : 'ui.overtime.pacts.create_dialog.title',
                            )}
                        </DialogTitle>
                    </DialogHeader>

                    {!employeeId && (
                        <FormField
                            label={t('ui.overtime.pacts.form.employee')}
                            htmlFor="user_id"
                            required
                            error={errors.user_id}
                        >
                            <Combobox
                                id="user_id"
                                options={employeeOptions ?? []}
                                value={data.user_id}
                                onChange={(value) => setData('user_id', value)}
                                placeholder={t(
                                    'ui.overtime.pacts.form.employee_placeholder',
                                )}
                                searchPlaceholder={t(
                                    'ui.overtime.pacts.form.employee_search',
                                )}
                                emptyLabel={t(
                                    'ui.overtime.pacts.form.employee_empty',
                                )}
                                modal
                            />
                        </FormField>
                    )}

                    <FormField
                        label={t('ui.overtime.pacts.form.start_date')}
                        htmlFor="start_date"
                        required
                        error={errors.start_date}
                    >
                        <Input
                            id="start_date"
                            type="date"
                            value={data.start_date}
                            onChange={(e) =>
                                setData('start_date', e.target.value)
                            }
                        />
                    </FormField>

                    <FormField
                        label={t('ui.overtime.pacts.form.end_date')}
                        htmlFor="end_date"
                        required
                        error={errors.end_date}
                    >
                        <Input
                            id="end_date"
                            type="date"
                            value={data.end_date}
                            onChange={(e) =>
                                setData('end_date', e.target.value)
                            }
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
                                isEdit
                                    ? 'ui.overtime.pacts.edit_dialog.submit'
                                    : 'ui.overtime.pacts.create_dialog.submit',
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
