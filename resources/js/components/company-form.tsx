import { Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Combobox } from '@/components/combobox';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
        <form onSubmit={submit} className="grid max-w-3xl gap-8">
            <section className="grid gap-6">
                <h2 className="text-sm font-medium text-muted-foreground">
                    {t('ui.companies.form.details')}
                </h2>

                <div className="grid gap-6 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="social_reason">
                            {t('ui.companies.form.social_reason')}
                        </Label>
                        <Input
                            id="social_reason"
                            value={data.social_reason}
                            onChange={(e) =>
                                setData('social_reason', e.target.value)
                            }
                            required
                            autoFocus
                        />
                        <InputError message={errors.social_reason} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="rut">
                            {t('ui.companies.form.rut')}
                        </Label>
                        <Input
                            id="rut"
                            value={data.rut}
                            onChange={(e) => setData('rut', e.target.value)}
                            placeholder={t('ui.companies.form.rut_placeholder')}
                            required
                        />
                        <InputError message={errors.rut} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="business_line">
                            {t('ui.companies.form.business_line')}
                        </Label>
                        <Input
                            id="business_line"
                            value={data.business_line}
                            onChange={(e) =>
                                setData('business_line', e.target.value)
                            }
                            required
                        />
                        <InputError message={errors.business_line} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="company_type">
                            {t('ui.companies.form.company_type')}
                        </Label>
                        <Input
                            id="company_type"
                            value={data.company_type}
                            onChange={(e) =>
                                setData('company_type', e.target.value)
                            }
                            required
                        />
                        <InputError message={errors.company_type} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">
                            {t('ui.companies.form.email')}
                        </Label>
                        <Input
                            id="email"
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />
                        <InputError message={errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="phone">
                            {t('ui.companies.form.phone')}
                        </Label>
                        <Input
                            id="phone"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            required
                        />
                        <InputError message={errors.phone} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="region_id">
                            {t('ui.companies.form.region')}
                        </Label>
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
                        <InputError message={errors.region_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="commune_id">
                            {t('ui.companies.form.commune')}
                        </Label>
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
                        <InputError message={errors.commune_id} />
                    </div>

                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="address">
                            {t('ui.companies.form.address')}
                        </Label>
                        <Input
                            id="address"
                            value={data.address}
                            onChange={(e) => setData('address', e.target.value)}
                            placeholder={t('ui.companies.form.address_hint')}
                            required
                        />
                        <InputError message={errors.address} />
                    </div>
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
                            <div className="grid gap-2">
                                <Label htmlFor={`rep-${index}-rut`}>
                                    {t('ui.companies.form.rep_rut')}
                                </Label>
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
                                    required
                                />
                                <InputError
                                    message={
                                        fieldErrors[
                                            `representatives.${index}.rut`
                                        ]
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`rep-${index}-email`}>
                                    {t('ui.companies.form.rep_email')}
                                </Label>
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
                                    required
                                />
                                <InputError
                                    message={
                                        fieldErrors[
                                            `representatives.${index}.email`
                                        ]
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`rep-${index}-first_name`}>
                                    {t('ui.companies.form.rep_first_name')}
                                </Label>
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
                                    required
                                />
                                <InputError
                                    message={
                                        fieldErrors[
                                            `representatives.${index}.first_name`
                                        ]
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor={`rep-${index}-last_name`}>
                                    {t('ui.companies.form.rep_last_name')}
                                </Label>
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
                                    required
                                />
                                <InputError
                                    message={
                                        fieldErrors[
                                            `representatives.${index}.last_name`
                                        ]
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`rep-${index}-second_last_name`}
                                >
                                    {t(
                                        'ui.companies.form.rep_second_last_name',
                                    )}
                                </Label>
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
                                <InputError
                                    message={
                                        fieldErrors[
                                            `representatives.${index}.second_last_name`
                                        ]
                                    }
                                />
                            </div>

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
