import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import { store, update } from '@/routes/positions';

export type PositionFormTarget = { id: number; name: string } | null;

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** When set, the dialog renames this position; otherwise it creates one. */
    position?: PositionFormTarget;
};

export default function PositionFormDialog({
    open,
    onOpenChange,
    position,
}: Props) {
    const { t } = useTranslations();
    const isEdit = Boolean(position);
    const {
        data,
        setData,
        post,
        patch,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm<{ name: string }>({ name: '' });

    // Sync the field with the target whenever the dialog is (re)opened.
    useEffect(() => {
        if (open) {
            setData('name', position?.name ?? '');
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, position?.id]);

    function submit(event: FormEvent) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        };

        if (isEdit && position) {
            patch(update(position.id).url, options);
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
                                    ? 'ui.positions.edit_dialog.title'
                                    : 'ui.positions.create_dialog.title',
                            )}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="name">
                            {t('ui.positions.form.name')}
                        </Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder={t(
                                'ui.positions.form.name_placeholder',
                            )}
                            required
                            autoFocus
                        />
                        <InputError message={errors.name} />
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
                                    ? 'ui.positions.edit_dialog.submit'
                                    : 'ui.positions.create_dialog.submit',
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
