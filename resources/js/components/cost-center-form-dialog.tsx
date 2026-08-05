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
import { store, update } from '@/routes/cost-centers';

export type CostCenterFormTarget = {
    id: number;
    name: string;
    code: string | null;
} | null;

type FormData = { name: string; code: string };

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** When set, the dialog edits this cost centre; otherwise it creates one. */
    costCenter?: CostCenterFormTarget;
};

export default function CostCenterFormDialog({
    open,
    onOpenChange,
    costCenter,
}: Props) {
    const { t } = useTranslations();
    const isEdit = Boolean(costCenter);
    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm<FormData>({ name: '', code: '' });

    // Sync the fields with the target whenever the dialog is (re)opened.
    useEffect(() => {
        if (open) {
            setData({
                name: costCenter?.name ?? '',
                code: costCenter?.code ?? '',
            });
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, costCenter?.id]);

    function submit(event: FormEvent) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        };

        if (isEdit && costCenter) {
            put(update(costCenter.id).url, options);
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
                                    ? 'ui.cost_centers.edit_dialog.title'
                                    : 'ui.cost_centers.create_dialog.title',
                            )}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="name">
                            {t('ui.cost_centers.form.name')}
                        </Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder={t(
                                'ui.cost_centers.form.name_placeholder',
                            )}
                            required
                            autoFocus
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="code">
                            {t('ui.cost_centers.form.code')}
                        </Label>
                        <Input
                            id="code"
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value)}
                            placeholder={t(
                                'ui.cost_centers.form.code_placeholder',
                            )}
                        />
                        <p className="text-xs text-muted-foreground">
                            {t('ui.cost_centers.form.code_hint')}
                        </p>
                        <InputError message={errors.code} />
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
                                    ? 'ui.cost_centers.edit_dialog.submit'
                                    : 'ui.cost_centers.create_dialog.submit',
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
