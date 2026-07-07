import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import PremiseForm from '@/components/premise-form';
import type { Option, PremiseFormData } from '@/components/premise-form';
import { useTranslations } from '@/hooks/use-translations';
import { update } from '@/routes/premises';

type Premise = {
    id: number;
    company_id: number | null;
    name: string;
    code: string | null;
    country: string | null;
    region: string | null;
    commune: string | null;
    address: string | null;
    lat: number | null;
    lng: number | null;
    responsable_name: string | null;
    responsable_email: string | null;
    responsable_phone: string | null;
};

type Props = {
    premise: Premise;
    companies: Option[];
};

export default function EditPremise({ premise, companies }: Props) {
    const { t } = useTranslations();

    const initial: PremiseFormData = {
        company_id: premise.company_id ? String(premise.company_id) : '',
        name: premise.name,
        code: premise.code ?? '',
        country: premise.country ?? '',
        region: premise.region ?? '',
        commune: premise.commune ?? '',
        address: premise.address ?? '',
        lat: premise.lat !== null ? String(premise.lat) : '',
        lng: premise.lng !== null ? String(premise.lng) : '',
        responsable_name: premise.responsable_name ?? '',
        responsable_email: premise.responsable_email ?? '',
        responsable_phone: premise.responsable_phone ?? '',
    };

    return (
        <>
            <Head title={t('ui.premises.edit.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.premises.edit.title')}
                    description={t('ui.premises.edit.description')}
                />

                <PremiseForm
                    companies={companies}
                    method="patch"
                    action={update(premise.id).url}
                    submitLabel={t('ui.premises.edit.submit')}
                    initial={initial}
                />
            </div>
        </>
    );
}
