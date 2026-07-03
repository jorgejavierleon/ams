import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function DtLogin() {
    return (
        <>
            <Head title="Login DT" />

            <Form
                action="/dt/login"
                method="post"
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo institucional</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="email"
                                placeholder="inspector@dt.gov.cl"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <div className="flex items-center">
                                <Label htmlFor="password">Clave</Label>
                                <TextLink
                                    href="/dt/forgot-password"
                                    className="ml-auto text-sm"
                                    tabIndex={5}
                                >
                                    Solicitar clave
                                </TextLink>
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                                placeholder="Clave"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <Button
                            type="submit"
                            className="mt-4 w-full"
                            tabIndex={3}
                            disabled={processing}
                            data-test="dt-login-button"
                        >
                            {processing && <Spinner />}
                            Ingresar
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

DtLogin.layout = {
    title: 'AMS – Portal DT',
    description: 'Acceso exclusivo para inspectores de la Dirección del Trabajo',
};
