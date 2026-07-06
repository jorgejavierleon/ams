import { Head } from '@inertiajs/react';
import CompanyForm from '@/components/company-form';
import type { CompanyFormData, Option } from '@/components/company-form';
import Heading from '@/components/heading';
import { useTranslations } from '@/hooks/use-translations';
import { update } from '@/routes/companies';

type Company = {
    id: number;
    rut: string;
    social_reason: string;
    business_line: string;
    email: string;
    region_id: number | null;
    commune_id: number | null;
    address: string;
    phone: string;
    company_type: string;
    is_est: boolean;
    is_active: boolean;
    representatives: Array<{
        id: number;
        rut: string;
        first_name: string;
        last_name: string;
        second_last_name: string | null;
        email: string;
    }>;
};

type Props = {
    company: Company;
    regions: Option[];
};

export default function EditCompany({ company, regions }: Props) {
    const { t } = useTranslations();

    const initial: CompanyFormData = {
        rut: company.rut,
        social_reason: company.social_reason,
        business_line: company.business_line,
        email: company.email,
        region_id: company.region_id ? String(company.region_id) : '',
        commune_id: company.commune_id ? String(company.commune_id) : '',
        address: company.address,
        phone: company.phone,
        company_type: company.company_type,
        is_est: company.is_est,
        is_active: company.is_active,
        representatives: company.representatives.map((representative) => ({
            id: representative.id,
            rut: representative.rut,
            first_name: representative.first_name,
            last_name: representative.last_name,
            second_last_name: representative.second_last_name ?? '',
            email: representative.email,
        })),
    };

    return (
        <>
            <Head title={t('ui.companies.edit.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.companies.edit.title')}
                    description={t('ui.companies.edit.description')}
                />

                <CompanyForm
                    regions={regions}
                    method="patch"
                    action={update(company.id).url}
                    submitLabel={t('ui.companies.edit.submit')}
                    initial={initial}
                />
            </div>
        </>
    );
}
