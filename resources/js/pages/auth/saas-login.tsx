import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function SaasLogin() {
    return (
        <>
            <Head title="Login SaaS" />

            <Form
                action="/saas/login"
                method="post"
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo electrónico</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="email"
                                placeholder="admin@example.com"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">Contraseña</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                tabIndex={2}
                                autoComplete="current-password"
                                placeholder="Contraseña"
                            />
                            <InputError message={errors.password} />
                        </div>

                        <Button
                            type="submit"
                            className="mt-4 w-full"
                            tabIndex={3}
                            disabled={processing}
                            data-test="saas-login-button"
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

SaasLogin.layout = {
    title: 'AMS – Panel SaaS',
    description: 'Acceso exclusivo para administradores del sistema',
};
