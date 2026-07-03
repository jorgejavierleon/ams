import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function DtPasswordChange() {
    return (
        <>
            <Head title="Cambiar clave DT" />

            <Form
                action="/dt/password/change"
                method="post"
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="password">Nueva clave</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoFocus
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
                            <InputError message={errors.password_confirmation} />
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
        </>
    );
}

DtPasswordChange.layout = {
    title: 'Cambiar clave – Portal DT',
    description: 'Su clave ha expirado. Por favor establezca una nueva clave para continuar.',
};
