import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes/dt';

export default function DtPasswordChange() {
    return (
        <>
            <Head title="Cambiar clave DT" />

            <Form
                action="/dt/password/change"
                method="post"
                resetOnSuccess={[
                    'current_password',
                    'password',
                    'password_confirmation',
                ]}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="current_password">
                                Clave actual
                            </Label>
                            <PasswordInput
                                id="current_password"
                                name="current_password"
                                required
                                autoFocus
                                autoComplete="current-password"
                                placeholder="Clave actual"
                            />
                            <InputError message={errors.current_password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Nueva clave</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="new-password"
                                placeholder="Nueva clave"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Confirmar clave
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                                placeholder="Confirmar clave"
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>

                        <Button
                            type="submit"
                            className="mt-4 w-full"
                            disabled={processing}
                            data-test="dt-password-change-button"
                        >
                            {processing && <Spinner />}
                            Cambiar clave
                        </Button>
                    </div>
                )}
            </Form>

            <div className="mt-4 space-x-1 text-center text-sm text-muted-foreground">
                <span>Volver al</span>
                <TextLink href={dashboard()}>panel</TextLink>
            </div>
        </>
    );
}

DtPasswordChange.layout = {
    title: 'Cambiar clave – Portal DT',
    description: 'Ingrese su clave actual y defina una nueva clave de acceso.',
};
