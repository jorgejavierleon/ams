import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { store } from '@/routes/dt/marks/validate';

type Mark = {
    employee_name: string | null;
    employee_rut: string | null;
    employer_name: string | null;
    employer_rut: string | null;
    date_time: string;
    type: string;
    premise_name: string | null;
    premise_address: string | null;
    coordinates: string | null;
    checksum: string;
};

type Props = {
    mark: Mark | null;
};

export default function ValidateMark({ mark }: Props) {
    const { t } = useTranslations();

    const fields: { label: string; value: string | null }[] = mark
        ? [
              {
                  label: t('ui.dt.marks.validate.employee_name'),
                  value: mark.employee_name,
              },
              {
                  label: t('ui.dt.marks.validate.employee_rut'),
                  value: mark.employee_rut,
              },
              {
                  label: t('ui.dt.marks.validate.employer_name'),
                  value: mark.employer_name,
              },
              {
                  label: t('ui.dt.marks.validate.employer_rut'),
                  value: mark.employer_rut,
              },
              {
                  label: t('ui.dt.marks.validate.date_time'),
                  value: mark.date_time,
              },
              { label: t('ui.dt.marks.validate.type'), value: mark.type },
              {
                  label: t('ui.dt.marks.validate.premise_name'),
                  value: mark.premise_name,
              },
              {
                  label: t('ui.dt.marks.validate.premise_address'),
                  value: mark.premise_address,
              },
              {
                  label: t('ui.dt.marks.validate.coordinates'),
                  value: mark.coordinates,
              },
              {
                  label: t('ui.dt.marks.validate.checksum_value'),
                  value: mark.checksum,
              },
          ]
        : [];

    return (
        <>
            <Head title={t('ui.dt.marks.validate.title')} />

            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <Heading
                    title={t('ui.dt.marks.validate.title')}
                    description={t('ui.dt.marks.validate.description')}
                />

                <Card>
                    <CardContent className="pt-6">
                        <Form {...store.form()} className="flex flex-col gap-4">
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="checksum">
                                            {t('ui.dt.marks.validate.checksum')}
                                        </Label>
                                        <Input
                                            id="checksum"
                                            name="checksum"
                                            required
                                            autoFocus
                                            autoComplete="off"
                                            placeholder={t(
                                                'ui.dt.marks.validate.checksum_placeholder',
                                            )}
                                            className="font-mono"
                                        />
                                        <InputError message={errors.checksum} />
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="w-full sm:w-auto"
                                        data-test="validate-mark-button"
                                    >
                                        {processing && <Spinner />}
                                        {t('ui.dt.marks.validate.submit')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {mark && (
                    <Card data-test="validate-mark-result">
                        <CardHeader>
                            <CardTitle>
                                {t('ui.dt.marks.validate.result_title')}
                            </CardTitle>
                            <CardDescription>
                                {t('ui.dt.marks.validate.result_description')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                                {fields.map((field) => (
                                    <div
                                        key={field.label}
                                        className="flex flex-col gap-0.5 border-b border-border/60 pb-2"
                                    >
                                        <dt className="text-xs font-medium text-muted-foreground">
                                            {field.label}
                                        </dt>
                                        <dd className="text-sm break-words">
                                            {field.value ??
                                                t(
                                                    'ui.dt.marks.validate.not_available',
                                                )}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
