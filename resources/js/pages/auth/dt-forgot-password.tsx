import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function DtForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Solicitar clave DT" />

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <Form
                action="/dt/forgot-password"
                method="post"
                className="space-y-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo institucional</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                autoFocus
                                placeholder="inspector@dt.gov.cl"
                            />
                            <InputError message={errors.email} />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                            data-test="dt-forgot-password-button"
                        >
                            {processing && <Spinner />}
                            Solicitar clave
                        </Button>
                    </>
                )}
            </Form>

            <div className="space-x-1 text-center text-sm text-muted-foreground">
                <span>Volver a</span>
                <TextLink href="/dt/login">ingresar</TextLink>
            </div>
        </>
    );
}

DtForgotPassword.layout = {
    title: 'Solicitar clave – Portal DT',
    description: 'Ingrese su correo @dt.gov.cl para recibir una clave de acceso',
};
