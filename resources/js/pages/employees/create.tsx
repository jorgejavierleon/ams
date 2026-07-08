import { Head } from '@inertiajs/react';
import EmployeeForm from '@/components/employee-form';
import type {
    EmployeeFormData,
    EmployeeFormOptions,
} from '@/components/employee-form';
import Heading from '@/components/heading';
import { useTranslations } from '@/hooks/use-translations';
import { store } from '@/routes/employees';

type Props = {
    options: EmployeeFormOptions;
};

const emptyEmployee: EmployeeFormData = {
    first_name: '',
    last_name: '',
    second_last_name: '',
    email: '',
    personal_email: '',
    password: '',
    rut: '',
    nationality: '',
    gender: '',
    is_active: true,
    company_id: '',
    premise_id: '',
    position_id: '',
    supervisor_id: '',
    contract_start_date: '',
    contract_end_date: '',
    is_admin: false,
    vacation_days: '0',
    additional_vacation_days: '0',
    administrative_days: '0',
    has_additional_sundays: false,
    phone: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    timezone: 'America/Santiago',
    avatar: null,
};

export default function CreateEmployee({ options }: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={t('ui.employees.create.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.employees.create.title')}
                    description={t('ui.employees.create.description')}
                />

                <EmployeeForm
                    method="post"
                    action={store().url}
                    submitLabel={t('ui.employees.create.submit')}
                    options={options}
                    initial={emptyEmployee}
                />
            </div>
        </>
    );
}
