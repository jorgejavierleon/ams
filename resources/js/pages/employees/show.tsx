import { Deferred, Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import type { ReactNode } from 'react';
import type { ComboboxOption } from '@/components/combobox';
import Heading from '@/components/heading';
import {
    ShiftAssignments
    
} from '@/components/shift-assignments';
import type {ShiftAssignment} from '@/components/shift-assignments';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTranslations } from '@/hooks/use-translations';
import { edit } from '@/routes/employees';

type Employee = {
    id: number;
    name: string;
    first_name: string | null;
    last_name: string | null;
    second_last_name: string | null;
    email: string;
    personal_email: string | null;
    rut: string | null;
    avatar: string | null;
    phone: string | null;
    nationality: string | null;
    gender: string | null;
    company: string | null;
    premise: string | null;
    position: string | null;
    supervisor: string | null;
    contract_start_date: string | null;
    contract_end_date: string | null;
    vacation_days: number;
    additional_vacation_days: number;
    administrative_days: number;
    has_additional_sundays: boolean;
    is_active: boolean;
    is_admin: boolean;
    timezone: string;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
};

type Shifts = {
    assignments: ShiftAssignment[];
    shiftOptions: ComboboxOption[];
};

type VacationBalance = {
    used: number;
    available: number;
    total: number;
};

type Props = {
    employee: Employee;
    shifts?: Shifts;
    vacationBalance: VacationBalance;
};

function Field({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="grid gap-1">
            <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="text-sm">{value || '—'}</dd>
        </div>
    );
}

export default function ShowEmployee({
    employee,
    shifts,
    vacationBalance,
}: Props) {
    const { t } = useTranslations();

    return (
        <>
            <Head title={employee.name} />

            <div className="space-y-6 p-6">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <Avatar className="size-14">
                            {employee.avatar ? (
                                <AvatarImage src={employee.avatar} alt="" />
                            ) : null}
                            <AvatarFallback>
                                {employee.name.charAt(0).toUpperCase()}
                            </AvatarFallback>
                        </Avatar>
                        <div className="space-y-1">
                            <Heading
                                title={
                                    <span className="flex items-center gap-2">
                                        {employee.name}
                                        {employee.is_admin && (
                                            <Badge variant="secondary">
                                                {t(
                                                    'ui.employees.columns.admin_badge',
                                                )}
                                            </Badge>
                                        )}
                                        <Badge
                                            variant={
                                                employee.is_active
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                        >
                                            {employee.is_active
                                                ? t(
                                                      'ui.employees.filters.active_yes',
                                                  )
                                                : t(
                                                      'ui.employees.filters.active_no',
                                                  )}
                                        </Badge>
                                    </span>
                                }
                                description={employee.email}
                            />
                        </div>
                    </div>
                    <Button asChild>
                        <Link href={edit(employee.id)}>
                            <Pencil className="size-4" />
                            {t('ui.employees.actions.edit')}
                        </Link>
                    </Button>
                </div>

                <Tabs defaultValue="info">
                    <TabsList>
                        <TabsTrigger value="info">
                            {t('ui.employees.show.tab_info')}
                        </TabsTrigger>
                        <TabsTrigger value="labor">
                            {t('ui.employees.show.tab_labor')}
                        </TabsTrigger>
                        <TabsTrigger value="shifts">
                            {t('ui.employees.show.tab_shifts')}
                        </TabsTrigger>
                        <TabsTrigger value="documents">
                            {t('ui.employees.show.tab_documents')}
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="info">
                        <Card>
                            <CardContent className="grid gap-6 pt-6 sm:grid-cols-2 lg:grid-cols-3">
                                <Field
                                    label={t('ui.employees.form.rut')}
                                    value={employee.rut}
                                />
                                <Field
                                    label={t(
                                        'ui.employees.form.personal_email',
                                    )}
                                    value={employee.personal_email}
                                />
                                <Field
                                    label={t('ui.employees.form.phone')}
                                    value={employee.phone}
                                />
                                <Field
                                    label={t('ui.employees.form.nationality')}
                                    value={employee.nationality}
                                />
                                <Field
                                    label={t('ui.employees.form.gender')}
                                    value={employee.gender}
                                />
                                <Field
                                    label={t('ui.employees.form.timezone')}
                                    value={employee.timezone}
                                />
                                <Field
                                    label={t(
                                        'ui.employees.form.emergency_contact_name',
                                    )}
                                    value={employee.emergency_contact_name}
                                />
                                <Field
                                    label={t(
                                        'ui.employees.form.emergency_contact_phone',
                                    )}
                                    value={employee.emergency_contact_phone}
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="labor">
                        <Card>
                            <CardContent className="grid gap-6 pt-6 sm:grid-cols-2 lg:grid-cols-3">
                                <Field
                                    label={t('ui.employees.form.company')}
                                    value={employee.company}
                                />
                                <Field
                                    label={t('ui.employees.form.premise')}
                                    value={employee.premise}
                                />
                                <Field
                                    label={t('ui.employees.form.position')}
                                    value={employee.position}
                                />
                                <Field
                                    label={t('ui.employees.form.supervisor')}
                                    value={employee.supervisor}
                                />
                                <Field
                                    label={t(
                                        'ui.employees.form.contract_start_date',
                                    )}
                                    value={employee.contract_start_date}
                                />
                                <Field
                                    label={t(
                                        'ui.employees.form.contract_end_date',
                                    )}
                                    value={employee.contract_end_date}
                                />
                                <Field
                                    label={t('ui.employees.form.vacation_days')}
                                    value={String(employee.vacation_days)}
                                />
                                <Field
                                    label={t(
                                        'ui.employees.form.additional_vacation_days',
                                    )}
                                    value={String(
                                        employee.additional_vacation_days,
                                    )}
                                />
                                <Field
                                    label={t(
                                        'ui.employees.form.administrative_days',
                                    )}
                                    value={String(employee.administrative_days)}
                                />
                                <Field
                                    label={t(
                                        'ui.employees.form.has_additional_sundays',
                                    )}
                                    value={
                                        employee.has_additional_sundays
                                            ? t('ui.employees.show.yes')
                                            : t('ui.employees.show.no')
                                    }
                                />
                                <Field
                                    label={t(
                                        'ui.employees.vacation_balance.title',
                                    )}
                                    value={
                                        <span className="grid gap-0.5">
                                            <span className="font-medium">
                                                {t(
                                                    'ui.employees.vacation_balance.available',
                                                    {
                                                        available: String(
                                                            vacationBalance.available,
                                                        ),
                                                    },
                                                )}
                                            </span>
                                            <span className="text-xs text-muted-foreground tabular-nums">
                                                {t(
                                                    'ui.employees.vacation_balance.summary',
                                                    {
                                                        used: String(
                                                            vacationBalance.used,
                                                        ),
                                                        total: String(
                                                            vacationBalance.total,
                                                        ),
                                                    },
                                                )}
                                            </span>
                                        </span>
                                    }
                                />
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="shifts">
                        <ShiftAssignments
                            employeeId={employee.id}
                            assignments={shifts?.assignments ?? []}
                            shiftOptions={shifts?.shiftOptions ?? []}
                        />
                    </TabsContent>

                    <TabsContent value="documents">
                        <Deferred
                            data="documents"
                            fallback={
                                <div className="space-y-2">
                                    <Skeleton className="h-10 w-full" />
                                    <Skeleton className="h-10 w-full" />
                                </div>
                            }
                        >
                            <Card>
                                <CardContent className="py-10 text-center text-sm text-muted-foreground">
                                    {t('ui.employees.show.documents_pending')}
                                </CardContent>
                            </Card>
                        </Deferred>
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}
