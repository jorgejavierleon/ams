import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslations } from '@/hooks/use-translations';
import { transferOwnership } from '@/routes/settings-organization';

type Owner = { id: number; name: string; email: string } | null;
type EligibleUser = { id: number; name: string; email: string };

type Props = {
    organization: { name: string };
    owner: Owner;
    isOwner: boolean;
    eligibleUsers: EligibleUser[];
};

export default function Organization({ owner, isOwner, eligibleUsers }: Props) {
    const { t } = useTranslations();
    const { data, setData, patch, processing, errors, reset } = useForm({
        user_id: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        patch(transferOwnership().url, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    }

    return (
        <>
            <Head title={t('ui.settings.ownership.head')} />

            <h1 className="sr-only">{t('ui.settings.ownership.head')}</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('ui.settings.ownership.title')}
                    description={t('ui.settings.ownership.description')}
                />

                <Card>
                    <CardContent className="space-y-4">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                {t('ui.settings.ownership.current_owner')}
                            </p>
                            <p className="font-medium">
                                {owner
                                    ? `${owner.name} (${owner.email})`
                                    : t('ui.settings.ownership.no_owner')}
                            </p>
                        </div>

                        {isOwner ? (
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button
                                        variant="outline"
                                        disabled={eligibleUsers.length === 0}
                                        data-test="transfer-ownership-button"
                                    >
                                        {t('ui.settings.ownership.transfer_button')}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogTitle>
                                        {t('ui.settings.ownership.confirm_title')}
                                    </DialogTitle>
                                    <DialogDescription>
                                        {t(
                                            'ui.settings.ownership.confirm_description',
                                        )}
                                    </DialogDescription>

                                    <form onSubmit={submit} className="space-y-4">
                                        <Select
                                            value={data.user_id}
                                            onValueChange={(value) =>
                                                setData('user_id', value)
                                            }
                                        >
                                            <SelectTrigger data-test="transfer-ownership-select">
                                                <SelectValue
                                                    placeholder={t(
                                                        'ui.settings.ownership.select_placeholder',
                                                    )}
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {eligibleUsers.map((user) => (
                                                    <SelectItem
                                                        key={user.id}
                                                        value={String(user.id)}
                                                    >
                                                        {user.name} ({user.email})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.user_id} />

                                        <DialogFooter className="gap-2">
                                            <DialogClose asChild>
                                                <Button
                                                    variant="secondary"
                                                    type="button"
                                                >
                                                    {t('ui.common.cancel')}
                                                </Button>
                                            </DialogClose>

                                            <Button
                                                type="submit"
                                                disabled={
                                                    processing || !data.user_id
                                                }
                                                data-test="confirm-transfer-ownership-button"
                                            >
                                                {t(
                                                    'ui.settings.ownership.transfer_button',
                                                )}
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                {t('ui.settings.ownership.not_owner_hint')}
                            </p>
                        )}

                        {isOwner && eligibleUsers.length === 0 && (
                            <p className="text-sm text-muted-foreground">
                                {t('ui.settings.ownership.no_eligible_users')}
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
