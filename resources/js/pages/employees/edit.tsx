import { Head } from '@inertiajs/react';
import EmployeeForm from '@/components/employee-form';
import type {
    EmployeeFormData,
    EmployeeFormOptions,
} from '@/components/employee-form';
import Heading from '@/components/heading';
import { useTranslations } from '@/hooks/use-translations';
import { update } from '@/routes/employees';

type Employee = {
    id: number;
    first_name: string | null;
    last_name: string | null;
    second_last_name: string | null;
    email: string;
    personal_email: string | null;
    rut: string | null;
    avatar: string | null;
    nationality: string | null;
    gender: string | null;
    is_active: boolean;
    company_id: number | null;
    premise_id: number | null;
    position_id: number | null;
    supervisor_id: number | null;
    contract_start_date: string | null;
    contract_end_date: string | null;
    is_admin: boolean;
    vacation_days: number;
    additional_vacation_days: number;
    administrative_days: number;
    has_additional_sundays: boolean;
    phone: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    timezone: string;
};

type Props = {
    employee: Employee;
    options: EmployeeFormOptions;
};

function toId(value: number | null): string {
    return value === null ? '' : String(value);
}

export default function EditEmployee({ employee, options }: Props) {
    const { t } = useTranslations();

    const initial: EmployeeFormData = {
        first_name: employee.first_name ?? '',
        last_name: employee.last_name ?? '',
        second_last_name: employee.second_last_name ?? '',
        email: employee.email,
        personal_email: employee.personal_email ?? '',
        password: '',
        rut: employee.rut ?? '',
        nationality: employee.nationality ?? '',
        gender: employee.gender ?? '',
        is_active: employee.is_active,
        company_id: toId(employee.company_id),
        premise_id: toId(employee.premise_id),
        position_id: toId(employee.position_id),
        supervisor_id: toId(employee.supervisor_id),
        contract_start_date: employee.contract_start_date ?? '',
        contract_end_date: employee.contract_end_date ?? '',
        is_admin: employee.is_admin,
        vacation_days: String(employee.vacation_days),
        additional_vacation_days: String(employee.additional_vacation_days),
        administrative_days: String(employee.administrative_days),
        has_additional_sundays: employee.has_additional_sundays,
        phone: employee.phone ?? '',
        emergency_contact_name: employee.emergency_contact_name ?? '',
        emergency_contact_phone: employee.emergency_contact_phone ?? '',
        timezone: employee.timezone,
        avatar: null,
    };

    return (
        <>
            <Head title={t('ui.employees.edit.title')} />

            <div className="space-y-6 p-6">
                <Heading
                    title={t('ui.employees.edit.title')}
                    description={t('ui.employees.edit.description')}
                />

                <EmployeeForm
                    method="patch"
                    action={update(employee.id).url}
                    submitLabel={t('ui.employees.edit.submit')}
                    options={options}
                    initial={initial}
                    currentAvatar={employee.avatar}
                />
            </div>
        </>
    );
}
