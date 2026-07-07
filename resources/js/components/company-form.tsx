import { Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Combobox } from '@/components/combobox';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useTranslations } from '@/hooks/use-translations';
import { index } from '@/routes/companies';
import { communes as communesRoute } from '@/routes/regions';

export type Option = { value: number; label: string };

export type RepresentativeData = {
    id?: number;
    rut: string;
    first_name: string;
    last_name: string;
    second_last_name: string;
    email: string;
};

export type CompanyFormData = {
    rut: string;
    social_reason: string;
    business_line: string;
    email: string;
    region_id: string;
    commune_id: string;
    address: string;
    phone: string;
    company_type: string;
    is_est: boolean;
    is_active: boolean;
    representatives: RepresentativeData[];
};

type Props = {
    regions: Option[];
    method: 'post' | 'patch';
    action: string;
    submitLabel: string;
    initial?: CompanyFormData;
};

const emptyRepresentative: RepresentativeData = {
    rut: '',
    first_name: '',
    last_name: '',
    second_last_name: '',
    email: '',
};

function blankForm(): CompanyFormData {
    return {
        rut: '',
        social_reason: '',
        business_line: '',
        email: '',
        region_id: '',
        commune_id: '',
        address: '',
        phone: '',
        company_type: '',
        is_est: false,
        is_active: true,
        representatives: [],
    };
}

export default function CompanyForm({
    regions,
    method,
    action,
    submitLabel,
    initial,
}: Props) {
    const { t } = useTranslations();
    const { data, setData, post, patch, processing, errors } =
        useForm<CompanyFormData>(initial ?? blankForm());

    const fieldErrors = errors as Record<string, string>;

    const [communes, setCommunes] = useState<Option[]>([]);
    const [loadingCommunes, setLoadingCommunes] = useState(false);

    // Load the communes for the initially-selected region (edit screen).
    useEffect(() => {
        if (initial?.region_id) {
            void loadCommunes(initial.region_id);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    async function loadCommunes(regionId: string) {
        setLoadingCommunes(true);

        try {
            const response = await fetch(
                communesRoute({ region: Number(regionId) }).url,
                { headers: { Accept: 'application/json' } },
            );
            setCommunes((await response.json()) as Option[]);
        } finally {
            setLoadingCommunes(false);
        }
    }

    function handleRegionChange(value: string) {
        setData((current) => ({
            ...current,
            region_id: value,
            commune_id: '',
        }));
        setCommunes([]);
        void loadCommunes(value);
    }

    function updateRepresentative(
        index: number,
        field: keyof RepresentativeData,
        value: string,
    ) {
        setData(
            'representatives',
            data.representatives.map((representative, current) =>
                current === index
                    ? { ...representative, [field]: value }
                    : representative,
            ),
        );
    }

    function addRepresentative() {
        setData('representatives', [
            ...data.representatives,
            { ...emptyRepresentative },
        ]);
    }

    function removeRepresentative(index: number) {
        setData(
            'representatives',
            data.representatives.filter((_, current) => current !== index),
        );
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

    // Combobox works with string values so it slots into the form fields.
    const regionOptions = regions.map((region) => ({
        value: String(region.value),
        label: region.label,
    }));
    const communeOptions = communes.map((commune) => ({
        value: String(commune.value),
        label: commune.label,
    }));

    return (
        // Validation is server-driven; `noValidate` disables native browser
        // validation so Inertia surfaces all errors at once, translated.
        <form onSubmit={submit} noValidate className="grid max-w-3xl gap-8">
            <section className="grid gap-6">
                <h2 className="text-sm font-medium text-muted-foreground">
                    {t('ui.companies.form.details')}
                </h2>

                <div className="grid gap-6 sm:grid-cols-2">
                    <FormField
                        label={t('ui.companies.form.social_reason')}
                        htmlFor="social_reason"
                        required
                        error={errors.social_reason}
                    >
                        <Input
                            id="social_reason"
                            value={data.social_reason}
                            onChange={(e) =>
                                setData('social_reason', e.target.value)
                            }
                            autoFocus
                        />
                    </FormField>

                    <FormField
                        label={t('ui.companies.form.rut')}
                        htmlFor="rut"
                        required
                        error={errors.rut}
                    >
                        <Input
                            id="rut"
                            value={data.rut}
                            onChange={(e) => setData('rut', e.target.value)}
                            placeholder={t('ui.companies.form.rut_placeholder')}
                        />
                    </FormField>

                    <FormField
                        label={t('ui.companies.form.business_line')}
                        htmlFor="business_line"
                        required
                        error={errors.business_line}
                    >
                        <Input
                            id="business_line"
                            value={data.business_line}
                            onChange={(e) =>
                                setData('business_line', e.target.value)
                            }
                        />
                    </FormField>

                    <FormField
                        label={t('ui.companies.form.company_type')}
                        htmlFor="company_type"
                        required
                        error={errors.company_type}
                    >
                        <Input
                            id="company_type"
                            value={data.company_type}
                            onChange={(e) =>
                                setData('company_type', e.target.value)
                            }
                        />
                    </FormField>

                    <FormField
                        label={t('ui.companies.form.email')}
                        htmlFor="email"
                        required
                        error={errors.email}
                    >
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                    </FormField>

                    <FormField
                        label={t('ui.companies.form.phone')}
                        htmlFor="phone"
                        required
                        error={errors.phone}
                    >
                        <Input
                            id="phone"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                        />
                    </FormField>

                    <FormField
                        label={t('ui.companies.form.region')}
                        htmlFor="region_id"
                        required
                        error={errors.region_id}
                    >
                        <Combobox
                            id="region_id"
                            options={regionOptions}
                            value={data.region_id}
                            onChange={handleRegionChange}
                            placeholder={t(
                                'ui.companies.form.region_placeholder',
                            )}
                            searchPlaceholder={t(
                                'ui.companies.form.region_search',
                            )}
                            emptyLabel={t('ui.companies.form.region_empty')}
                        />
                    </FormField>

                    <FormField
                        label={t('ui.companies.form.commune')}
                        htmlFor="commune_id"
                        required
                        error={errors.commune_id}
                    >
                        <Combobox
                            id="commune_id"
                            options={communeOptions}
                            value={data.commune_id}
                            onChange={(value) => setData('commune_id', value)}
                            disabled={!data.region_id || loadingCommunes}
                            placeholder={t(
                                loadingCommunes
                                    ? 'ui.companies.form.commune_loading'
                                    : !data.region_id
                                      ? 'ui.companies.form.commune_region_first'
                                      : 'ui.companies.form.commune_placeholder',
                            )}
                            searchPlaceholder={t(
                                'ui.companies.form.commune_search',
                            )}
                            emptyLabel={t('ui.companies.form.commune_empty')}
                        />
                    </FormField>

                    <FormField
                        label={t('ui.companies.form.address')}
                        htmlFor="address"
                        required
                        error={errors.address}
                        hint={t('ui.companies.form.address_hint')}
                        className="sm:col-span-2"
                    >
                        <Input
                            id="address"
                            value={data.address}
                            onChange={(e) => setData('address', e.target.value)}
                        />
                    </FormField>
                </div>

                <div className="flex flex-wrap gap-6">
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={data.is_active}
                            onCheckedChange={(checked) =>
                                setData('is_active', checked === true)
                            }
                        />
                        {t('ui.companies.form.is_active')}
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={data.is_est}
                            onCheckedChange={(checked) =>
                                setData('is_est', checked === true)
                            }
                        />
                        {t('ui.companies.form.is_est')}
                    </label>
                </div>
            </section>

            <section className="grid gap-4">
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <h2 className="text-sm font-medium text-muted-foreground">
                            {t('ui.companies.form.representatives')}
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            {t('ui.companies.form.representatives_hint')}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={addRepresentative}
                    >
                        <Plus className="size-4" />
                        {t('ui.companies.form.add_representative')}
                    </Button>
                </div>

                {data.representatives.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                        {t('ui.companies.form.no_representatives')}
                    </p>
                ) : (
                    data.representatives.map((representative, index) => (
                        <div
                            key={index}
                            className="grid gap-4 rounded-lg border p-4 sm:grid-cols-2"
                        >
                            <FormField
                                label={t('ui.companies.form.rep_rut')}
                                htmlFor={`rep-${index}-rut`}
                                required
                                error={
                                    fieldErrors[`representatives.${index}.rut`]
                                }
                            >
                                <Input
                                    id={`rep-${index}-rut`}
                                    value={representative.rut}
                                    onChange={(e) =>
                                        updateRepresentative(
                                            index,
                                            'rut',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>

                            <FormField
                                label={t('ui.companies.form.rep_email')}
                                htmlFor={`rep-${index}-email`}
                                required
                                error={
                                    fieldErrors[
                                        `representatives.${index}.email`
                                    ]
                                }
                            >
                                <Input
                                    id={`rep-${index}-email`}
                                    type="email"
                                    value={representative.email}
                                    onChange={(e) =>
                                        updateRepresentative(
                                            index,
                                            'email',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>

                            <FormField
                                label={t('ui.companies.form.rep_first_name')}
                                htmlFor={`rep-${index}-first_name`}
                                required
                                error={
                                    fieldErrors[
                                        `representatives.${index}.first_name`
                                    ]
                                }
                            >
                                <Input
                                    id={`rep-${index}-first_name`}
                                    value={representative.first_name}
                                    onChange={(e) =>
                                        updateRepresentative(
                                            index,
                                            'first_name',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>

                            <FormField
                                label={t('ui.companies.form.rep_last_name')}
                                htmlFor={`rep-${index}-last_name`}
                                required
                                error={
                                    fieldErrors[
                                        `representatives.${index}.last_name`
                                    ]
                                }
                            >
                                <Input
                                    id={`rep-${index}-last_name`}
                                    value={representative.last_name}
                                    onChange={(e) =>
                                        updateRepresentative(
                                            index,
                                            'last_name',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>

                            <FormField
                                label={t(
                                    'ui.companies.form.rep_second_last_name',
                                )}
                                htmlFor={`rep-${index}-second_last_name`}
                                error={
                                    fieldErrors[
                                        `representatives.${index}.second_last_name`
                                    ]
                                }
                            >
                                <Input
                                    id={`rep-${index}-second_last_name`}
                                    value={representative.second_last_name}
                                    onChange={(e) =>
                                        updateRepresentative(
                                            index,
                                            'second_last_name',
                                            e.target.value,
                                        )
                                    }
                                />
                            </FormField>

                            <div className="flex items-end justify-end">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="text-destructive hover:text-destructive"
                                    onClick={() => removeRepresentative(index)}
                                >
                                    <Trash2 className="size-4" />
                                    {t('ui.companies.form.remove')}
                                </Button>
                            </div>
                        </div>
                    ))
                )}
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
