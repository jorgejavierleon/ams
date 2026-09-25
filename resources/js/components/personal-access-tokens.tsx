import { router } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import TokenController from '@/actions/App/Http/Controllers/Settings/TokenController';
import { ConfirmDialog } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useClipboard } from '@/hooks/use-clipboard';
import { useTranslations } from '@/hooks/use-translations';

export type PersonalAccessToken = {
    id: number;
    name: string;
    created_at: string;
    last_used_at: string | null;
};

type NewToken = {
    name: string;
    plainTextToken: string;
};

type Props = {
    tokens: PersonalAccessToken[];
};

export function PersonalAccessTokens({ tokens }: Props) {
    const { t, formatDateTime } = useTranslations();
    const [copiedText, copy] = useClipboard();

    const [name, setName] = useState('');
    const [nameError, setNameError] = useState<string>();
    const [processing, setProcessing] = useState(false);
    const [revokeTarget, setRevokeTarget] =
        useState<PersonalAccessToken | null>(null);
    const [newToken, setNewToken] = useState<NewToken | null>(null);

    function handleCreate(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        router.post(
            TokenController.store().url,
            { name },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (errors) => setNameError(errors.name),
                onSuccess: () => {
                    setName('');
                    setNameError(undefined);
                },
                onFlash: (flash) => {
                    if (flash.newToken) {
                        setNewToken(flash.newToken as NewToken);
                    }
                },
            },
        );
    }

    function confirmRevoke() {
        if (!revokeTarget) {
            return;
        }

        router.delete(TokenController.destroy(revokeTarget.id).url, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setRevokeTarget(null),
        });
    }

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title={t('ui.settings.security.tokens.title')}
                description={t('ui.settings.security.tokens.description')}
            />

            <form
                onSubmit={handleCreate}
                className="flex items-end gap-4"
            >
                <div className="grid flex-1 gap-2">
                    <Label htmlFor="token-name">
                        {t('ui.settings.security.tokens.name')}
                    </Label>

                    <Input
                        id="token-name"
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        placeholder={t(
                            'ui.settings.security.tokens.name_placeholder',
                        )}
                        required
                    />

                    <InputError message={nameError} />
                </div>

                <Button
                    type="submit"
                    disabled={processing}
                    data-test="create-token-button"
                >
                    {t('ui.settings.security.tokens.create')}
                </Button>
            </form>

            {tokens.length === 0 ? (
                <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                    {t('ui.settings.security.tokens.empty')}
                </p>
            ) : (
                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    {t(
                                        'ui.settings.security.tokens.columns.name',
                                    )}
                                </TableHead>
                                <TableHead>
                                    {t(
                                        'ui.settings.security.tokens.columns.created_at',
                                    )}
                                </TableHead>
                                <TableHead>
                                    {t(
                                        'ui.settings.security.tokens.columns.last_used_at',
                                    )}
                                </TableHead>
                                <TableHead className="w-0" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {tokens.map((token) => (
                                <TableRow key={token.id}>
                                    <TableCell>{token.name}</TableCell>
                                    <TableCell>
                                        {formatDateTime(token.created_at)}
                                    </TableCell>
                                    <TableCell>
                                        {token.last_used_at
                                            ? formatDateTime(
                                                  token.last_used_at,
                                              )
                                            : t(
                                                  'ui.settings.security.tokens.never_used',
                                              )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-destructive hover:text-destructive"
                                                onClick={() =>
                                                    setRevokeTarget(token)
                                                }
                                            >
                                                {t(
                                                    'ui.settings.security.tokens.actions.revoke',
                                                )}
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            <ConfirmDialog
                open={revokeTarget !== null}
                onOpenChange={(open) => !open && setRevokeTarget(null)}
                title={t('ui.settings.security.tokens.revoke_dialog.title')}
                description={t(
                    'ui.settings.security.tokens.revoke_dialog.description',
                )}
                confirmLabel={t(
                    'ui.settings.security.tokens.revoke_dialog.confirm',
                )}
                onConfirm={confirmRevoke}
                processing={processing}
            />

            <Dialog
                open={newToken !== null}
                onOpenChange={(open) => !open && setNewToken(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t(
                                'ui.settings.security.tokens.created_dialog.title',
                            )}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'ui.settings.security.tokens.created_dialog.description',
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="rounded-md border bg-muted p-3 font-mono text-sm break-all">
                        {newToken?.plainTextToken}
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                newToken && copy(newToken.plainTextToken)
                            }
                        >
                            {copiedText === newToken?.plainTextToken ? (
                                <Check className="size-4" />
                            ) : (
                                <Copy className="size-4" />
                            )}
                            {copiedText === newToken?.plainTextToken
                                ? t(
                                      'ui.settings.security.tokens.created_dialog.copied',
                                  )
                                : t(
                                      'ui.settings.security.tokens.created_dialog.copy',
                                  )}
                        </Button>

                        <Button
                            type="button"
                            onClick={() => setNewToken(null)}
                            data-test="close-new-token-dialog"
                        >
                            {t(
                                'ui.settings.security.tokens.created_dialog.done',
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
