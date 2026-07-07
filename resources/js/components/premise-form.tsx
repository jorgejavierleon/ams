import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Combobox } from '@/components/combobox';
import { FormField } from '@/components/form-field';
import { MapErrorBoundary, MapPicker } from '@/components/map-picker';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { index } from '@/routes/premises';

export type Option = { value: number; label: string };

export type PremiseFormData = {
    company_id: string;
    name: string;
    code: string;
    country: string;
    region: string;
    commune: string;
    address: string;
    lat: string;
    lng: string;
    responsable_name: string;
    responsable_email: string;
    responsable_phone: string;
};

type Props = {
    companies: Option[];
    method: 'post' | 'patch';
    action: string;
    submitLabel: string;
    initial?: PremiseFormData;
};

function blankForm(): PremiseFormData {
    return {
        company_id: '',
        name: '',
        code: '',
        country: 'Chile',
        region: '',
        commune: '',
        address: '',
        lat: '',
        lng: '',
        responsable_name: '',
        responsable_email: '',
        responsable_phone: '',
    };
}

/** Round to the 8 decimals the `lat`/`lng` columns store, dropping noise. */
function formatCoordinate(value: number): string {
    return String(Number(value.toFixed(8)));
}

export default function PremiseForm({
    companies,
    method,
    action,
    submitLabel,
    initial,
}: Props) {
    const { t } = useTranslations();
    const { data, setData, post, patch, processing, errors } =
        useForm<PremiseFormData>(initial ?? blankForm());

    function handleMapChange(lat: number, lng: number) {
        setData((current) => ({
            ...current,
            lat: formatCoordinate(lat),
            lng: formatCoordinate(lng),
        }));
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        const options = { preserveScroll: true };

        if (method === 'patch') {
            patch(action, options);
        } else {
            post(action, options);
        }
    }

    const companyOptions = companies.map((company) => ({
        value: String(company.value),
        label: company.label,
    }));

    const latValue = data.lat !== '' ? Number(data.lat) : null;
    const lngValue = data.lng !== '' ? Number(data.lng) : null;
    const mapLat =
        latValue !== null && Number.isFinite(latValue) ? latValue : null;
    const mapLng =
        lngValue !== null && Number.isFinite(lngValue) ? lngValue : null;

    return (
        // Validation is server-driven; `noValidate` disables native browser
        // validation so Inertia surfaces all errors at once, translated.
        <form onSubmit={submit} noValidate className="grid max-w-3xl gap-8">
            <section className="grid gap-6">
                <h2 className="text-sm font-medium text-muted-foreground">
                    {t('ui.premises.form.details')}
                </h2>

                <div className="grid gap-6 sm:grid-cols-2">
                    <FormField
                        label={t('ui.premises.form.company')}
                        htmlFor="company_id"
                        required
                        error={errors.company_id}
                    >
                        <Combobox
                            id="company_id"
                            options={companyOptions}
                            value={data.company_id}
                            onChange={(value) => setData('company_id', value)}
                            placeholder={t(
                                'ui.premises.form.company_placeholder',
                            )}
                            searchPlaceholder={t(
                                'ui.premises.form.company_search',
                            )}
                            emptyLabel={t('ui.premises.form.company_empty')}
                        />
                    </FormField>

                    <FormField
                        label={t('ui.premises.form.name')}
                        htmlFor="name"
                        required
                        error={errors.name}
                    >
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            autoFocus
                        />
                    </FormField>

                    <FormField
                        label={t('ui.premises.form.code')}
                        htmlFor="code"
                        error={errors.code}
                    >
                        <Input
                            id="code"
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value)}
                        />
                    </FormField>

                    <FormField
                        label={t('ui.premises.form.address')}
                        htmlFor="address"
                        error={errors.address}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="address"
                            value={data.address}
                            onChange={(e) => setData('address', e.target.value)}
                        />
                    </FormField>

                    <FormField
                        label={t('ui.premises.form.country')}
                        htmlFor="country"
                        error={errors.country}
                    >
                        <Input
                            id="country"
                            value={data.country}
                            onChange={(e) => setData('country', e.target.value)}
                        />
                    </FormField>

                    <FormField
                        label={t('ui.premises.form.region')}
                        htmlFor="region"
                        error={errors.region}
                    >
                        <Input
                            id="region"
                            value={data.region}
                            onChange={(e) => setData('region', e.target.value)}
                        />
                    </FormField>

                    <FormField
                        label={t('ui.premises.form.commune')}
                        htmlFor="commune"
                        error={errors.commune}
                    >
                        <Input
                            id="commune"
                            value={data.commune}
                            onChange={(e) => setData('commune', e.target.value)}
                        />
                    </FormField>
                </div>
            </section>

            <section className="grid gap-4">
                <div>
                    <h2 className="text-sm font-medium text-muted-foreground">
                        {t('ui.premises.form.location')}
                    </h2>
                    <p className="text-xs text-muted-foreground">
                        {t('ui.premises.form.location_hint')}
                    </p>
                </div>

                <MapErrorBoundary
                    fallback={
                        <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                            {t('ui.premises.map.unavailable')}
                        </p>
                    }
                >
                    <MapPicker
                        lat={mapLat}
                        lng={mapLng}
                        onChange={handleMapChange}
                        addressHint={data.address}
                    />
                </MapErrorBoundary>

                <div className="grid gap-6 sm:grid-cols-2">
                    <FormField
                        label={t('ui.premises.form.lat')}
                        htmlFor="lat"
                        error={errors.lat}
                    >
                        <Input
                            id="lat"
                            inputMode="decimal"
                            value={data.lat}
                            onChange={(e) => setData('lat', e.target.value)}
                            placeholder="-33.44890000"
                        />
                    </FormField>

                    <FormField
                        label={t('ui.premises.form.lng')}
                        htmlFor="lng"
                        error={errors.lng}
                    >
                        <Input
                            id="lng"
                            inputMode="decimal"
                            value={data.lng}
                            onChange={(e) => setData('lng', e.target.value)}
                            placeholder="-70.66930000"
                        />
                    </FormField>
                </div>
            </section>

            <section className="grid gap-6">
                <h2 className="text-sm font-medium text-muted-foreground">
                    {t('ui.premises.form.responsable')}
                </h2>

                <div className="grid gap-6 sm:grid-cols-3">
                    <FormField
                        label={t('ui.premises.form.responsable_name')}
                        htmlFor="responsable_name"
                        error={errors.responsable_name}
                    >
                        <Input
                            id="responsable_name"
                            value={data.responsable_name}
                            onChange={(e) =>
                                setData('responsable_name', e.target.value)
                            }
                        />
                    </FormField>

                    <FormField
                        label={t('ui.premises.form.responsable_email')}
                        htmlFor="responsable_email"
                        error={errors.responsable_email}
                    >
                        <Input
                            id="responsable_email"
                            type="email"
                            value={data.responsable_email}
                            onChange={(e) =>
                                setData('responsable_email', e.target.value)
                            }
                        />
                    </FormField>

                    <FormField
                        label={t('ui.premises.form.responsable_phone')}
                        htmlFor="responsable_phone"
                        error={errors.responsable_phone}
                    >
                        <Input
                            id="responsable_phone"
                            value={data.responsable_phone}
                            onChange={(e) =>
                                setData('responsable_phone', e.target.value)
                            }
                        />
                    </FormField>
                </div>
            </section>

            <div className="flex items-center gap-3">
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    {submitLabel}
                </Button>
                <Button variant="ghost" asChild>
                    <Link href={index()}>{t('ui.common.cancel')}</Link>
                </Button>
            </div>
        </form>
    );
}
