import { Deferred, Head, Link, router } from '@inertiajs/react';
import { IdCard, Mail, Pencil, Phone, Power } from 'lucide-react';
import type { ComponentType, ReactNode } from 'react';
import type { ComboboxOption } from '@/components/combobox';
import { EmployeeLeaves } from '@/components/employee-leaves';
import type { EmployeeLeave } from '@/components/employee-leaves';
import { EmployeeOvertimePacts } from '@/components/employee-overtime-pacts';
import type { EmployeeOvertimePact } from '@/components/employee-overtime-pacts';
import { ShiftAssignments } from '@/components/shift-assignments';
import type { ShiftAssignment } from '@/components/shift-assignments';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useInitials } from '@/hooks/use-initials';
import { useTranslations } from '@/hooks/use-translations';
import { edit, toggleActive } from '@/routes/employees';

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
    cost_center: string | null;
    premise: string | null;
    position: string | null;
    supervisor: string | null;
    contract_start_date: string | null;
    contract_end_date: string | null;
    contract_type: string | null;
    vacation_days: number;
    additional_vacation_days: number;
    administrative_days: number;
    has_additional_sundays: boolean;
    overtime_rest_day_eligible: boolean;
    is_active: boolean;
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

type Can = {
    manageOvertimePacts: boolean;
};

type Props = {
    employee: Employee;
    shifts?: Shifts;
    overtimePacts?: EmployeeOvertimePact[];
    can: Can;
    vacationBalance: VacationBalance;
    leaves?: EmployeeLeave[];
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

function Section({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-4">
            <h4 className="text-sm font-semibold">{title}</h4>
            <dl className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {children}
            </dl>
        </div>
    );
}

function InfoRow({
    icon: Icon,
    label,
    value,
}: {
    icon: ComponentType<{ className?: string }>;
    label: string;
    value: ReactNode;
}) {
    return (
        <div className="flex min-w-0 items-start gap-2 text-sm">
            <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
            <span className="w-16 shrink-0 text-muted-foreground">
                {label}
            </span>
            <span className="min-w-0 flex-1 font-medium break-words">
                {value || '—'}
            </span>
        </div>
    );
}

/** Full years (one decimal) between contract_start_date and today. */
function tenureInYears(contractStartDate: string | null): string | null {
    if (!contractStartDate) {
        return null;
    }

    const start = new Date(contractStartDate);
    const years = (Date.now() - start.getTime()) / (365.25 * 24 * 60 * 60 * 1000);

    return years < 0 ? null : years.toFixed(1);
}

function EmployeeProfileCard({
    employee,
    shiftCount,
    vacationBalance,
    t,
}: {
    employee: Employee;
    shiftCount: number;
    vacationBalance: VacationBalance;
    t: ReturnType<typeof useTranslations>['t'];
}) {
    const getInitials = useInitials();
    const tenure = tenureInYears(employee.contract_start_date);
    const vacationPercent =
        vacationBalance.total > 0
            ? Math.min(
                  100,
                  Math.round(
                      (vacationBalance.available / vacationBalance.total) *
                          100,
                  ),
              )
            : 0;

    return (
        <Card className="min-w-0">
            <CardContent className="grid min-w-0 gap-5">
                <div className="grid min-w-0 justify-items-center gap-2 border-b pb-5 text-center">
                    <Avatar className="size-16">
                        {employee.avatar ? (
                            <AvatarImage src={employee.avatar} alt="" />
                        ) : null}
                        <AvatarFallback className="text-base">
                            {getInitials(employee.name)}
                        </AvatarFallback>
                    </Avatar>
                    <div>
                        <p className="font-semibold">{employee.name}</p>
                        <p className="text-sm text-muted-foreground">
                            {[employee.position, employee.premise]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                    </div>
                    <div className="flex flex-wrap justify-center gap-1.5">
                        <Badge
                            variant={
                                employee.is_active ? 'default' : 'outline'
                            }
                        >
                            {employee.is_active
                                ? t('ui.employees.filters.active_yes')
                                : t('ui.employees.filters.active_no')}
                        </Badge>
                        {employee.contract_type && (
                            <Badge variant="outline">
                                {employee.contract_type}
                            </Badge>
                        )}
                    </div>
                </div>

                <div className="grid min-w-0 grid-cols-2 gap-2.5 border-b pb-5">
                    <div className="grid gap-0.5 rounded-md bg-muted p-2.5 text-center">
                        <span className="text-lg font-bold tabular-nums">
                            {tenure ?? '—'}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {t('ui.employees.show.stats.tenure_years')}
                        </span>
                    </div>
                    <div className="grid gap-0.5 rounded-md bg-muted p-2.5 text-center">
                        <span className="text-lg font-bold tabular-nums">
                            {shiftCount}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {t('ui.employees.show.stats.shift_assignments')}
                        </span>
                    </div>
                </div>

                <div className="grid min-w-0 gap-2.5 border-b pb-5">
                    <InfoRow
                        icon={Mail}
                        label={t('ui.employees.form.email')}
                        value={employee.email}
                    />
                    <InfoRow
                        icon={Phone}
                        label={t('ui.employees.show.contact.phone')}
                        value={employee.phone}
                    />
                    <InfoRow
                        icon={IdCard}
                        label={t('ui.employees.show.contact.rut')}
                        value={employee.rut}
                    />
                </div>

                <div className="grid min-w-0 gap-1.5">
                    <div className="flex flex-wrap items-baseline justify-between gap-x-2 text-sm">
                        <span className="text-muted-foreground">
                            {t('ui.employees.vacation_balance.title')}
                        </span>
                        <span className="font-semibold tabular-nums">
                            {t('ui.employees.vacation_balance.available', {
                                available: String(vacationBalance.available),
                            })}
                        </span>
                    </div>
                    <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                        <div
                            className="h-full rounded-full bg-primary"
                            style={{ width: `${vacationPercent}%` }}
                        />
                    </div>
                    <p className="text-xs text-muted-foreground tabular-nums">
                        {t('ui.employees.vacation_balance.summary', {
                            used: String(vacationBalance.used),
                            total: String(vacationBalance.total),
                        })}
                    </p>
                </div>
            </CardContent>
        </Card>
    );
}

export default function ShowEmployee({
    employee,
    shifts,
    overtimePacts,
    can,
    vacationBalance,
    leaves,
}: Props) {
    const { t } = useTranslations();

    function toggleEmployeeActive() {
        router.patch(
            toggleActive(employee.id).url,
            {},
            { preserveScroll: true, preserveState: true },
        );
    }

    return (
        <>
            <Head title={employee.name} />

            <div className="space-y-6 p-6">
                <div className="flex justify-end gap-2">
                    <Button variant="outline" onClick={toggleEmployeeActive}>
                        <Power className="size-4" />
                        {employee.is_active
                            ? t('ui.employees.show.actions.deactivate')
                            : t('ui.employees.show.actions.activate')}
                    </Button>
                    <Button asChild>
                        <Link href={edit(employee.id)}>
                            <Pencil className="size-4" />
                            {t('ui.employees.actions.edit')}
                        </Link>
                    </Button>
                </div>

                <div className="grid min-w-0 items-start gap-5 lg:grid-cols-[380px_1fr]">
                    <EmployeeProfileCard
                        employee={employee}
                        shiftCount={shifts?.assignments.length ?? 0}
                        vacationBalance={vacationBalance}
                        t={t}
                    />

                    <Tabs defaultValue="info" className="min-w-0">
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
                            <TabsTrigger value="leaves">
                                {t('ui.employees.show.tab_leaves')}
                            </TabsTrigger>
                            <TabsTrigger value="documents">
                                {t('ui.employees.show.tab_documents')}
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="info">
                            <Card>
                                <CardContent className="grid gap-6 pt-6">
                                    <Section
                                        title={t(
                                            'ui.employees.show.sections.identity',
                                        )}
                                    >
                                        <Field
                                            label={t(
                                                'ui.employees.form.rut',
                                            )}
                                            value={employee.rut}
                                        />
                                        <Field
                                            label={t(
                                                'ui.employees.form.gender',
                                            )}
                                            value={employee.gender}
                                        />
                                        <Field
                                            label={t(
                                                'ui.employees.form.nationality',
                                            )}
                                            value={employee.nationality}
                                        />
                                    </Section>

                                    <Separator />

                                    <Section
                                        title={t(
                                            'ui.employees.show.sections.contact',
                                        )}
                                    >
                                        <Field
                                            label={t(
                                                'ui.employees.form.personal_email',
                                            )}
                                            value={employee.personal_email}
                                        />
                                        <Field
                                            label={t(
                                                'ui.employees.form.phone',
                                            )}
                                            value={employee.phone}
                                        />
                                        <Field
                                            label={t(
                                                'ui.employees.form.timezone',
                                            )}
                                            value={employee.timezone}
                                        />
                                    </Section>

                                    <Separator />

                                    <Section
                                        title={t(
                                            'ui.employees.show.sections.emergency_contact',
                                        )}
                                    >
                                        <Field
                                            label={t(
                                                'ui.employees.form.emergency_contact_name',
                                            )}
                                            value={
                                                employee.emergency_contact_name
                                            }
                                        />
                                        <Field
                                            label={t(
                                                'ui.employees.form.emergency_contact_phone',
                                            )}
                                            value={
                                                employee.emergency_contact_phone
                                            }
                                        />
                                    </Section>
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="labor">
                            <Card>
                                <CardContent className="grid gap-6 pt-6">
                                    <Section
                                        title={t(
                                            'ui.employees.show.sections.employment',
                                        )}
                                    >
                                        <Field
                                            label={t(
                                                'ui.employees.form.employer',
                                            )}
                                            value={employee.company}
                                        />
                                        <Field
                                            label={t(
                                                'ui.employees.form.cost_center',
                                            )}
                                            value={employee.cost_center}
                                        />
                                        <Field
                                            label={t(
                                                'ui.employees.form.premise',
                                            )}
                                            value={employee.premise}
                                        />
                                        <Field
                                            label={t(
                                                'ui.employees.form.position',
                                            )}
                                            value={employee.position}
                                        />
                                        <Field
                                            label={t(
                                                'ui.employees.form.supervisor',
                                            )}
                                            value={employee.supervisor}
                                        />
                                    </Section>

                                    <Separator />

                                    <Section
                                        title={t(
                                            'ui.employees.show.sections.contract',
                                        )}
                                    >
                                        <Field
                                            label={t(
                                                'ui.employees.form.contract_start_date',
                                            )}
                                            value={
                                                employee.contract_start_date
                                            }
                                        />
                                        <Field
                                            label={t(
                                                'ui.employees.form.contract_end_date',
                                            )}
                                            value={employee.contract_end_date}
                                        />
                                        <Field
                                            label={t(
                                                'ui.employees.form.contract_type',
                                            )}
                                            value={employee.contract_type}
                                        />
                                    </Section>

                                    <Separator />

                                    <Section
                                        title={t(
                                            'ui.employees.show.sections.benefits',
                                        )}
                                    >
                                        <Field
                                            label={t(
                                                'ui.employees.form.vacation_days',
                                            )}
                                            value={String(
                                                employee.vacation_days,
                                            )}
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
                                            value={String(
                                                employee.administrative_days,
                                            )}
                                        />
                                        <Field
                                            label={t(
                                                'ui.employees.form.has_additional_sundays',
                                            )}
                                            value={
                                                employee.has_additional_sundays
                                                    ? t(
                                                          'ui.employees.show.yes',
                                                      )
                                                    : t(
                                                          'ui.employees.show.no',
                                                      )
                                            }
                                        />
                                        <Field
                                            label={t(
                                                'ui.employees.form.overtime_rest_day_eligible',
                                            )}
                                            value={
                                                employee.overtime_rest_day_eligible
                                                    ? t(
                                                          'ui.employees.show.yes',
                                                      )
                                                    : t(
                                                          'ui.employees.show.no',
                                                      )
                                            }
                                        />
                                    </Section>
                                </CardContent>
                            </Card>
                        </TabsContent>

                        <TabsContent value="shifts" className="space-y-6">
                            <ShiftAssignments
                                employeeId={employee.id}
                                assignments={shifts?.assignments ?? []}
                                shiftOptions={shifts?.shiftOptions ?? []}
                            />
                            <EmployeeOvertimePacts
                                employeeId={employee.id}
                                employeeName={employee.name}
                                pacts={overtimePacts ?? []}
                                canManage={can.manageOvertimePacts}
                            />
                        </TabsContent>

                        <TabsContent value="leaves">
                            <Deferred
                                data="leaves"
                                fallback={
                                    <div className="space-y-2">
                                        <Skeleton className="h-10 w-full" />
                                        <Skeleton className="h-10 w-full" />
                                    </div>
                                }
                            >
                                <EmployeeLeaves leaves={leaves ?? []} />
                            </Deferred>
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
                                        {t(
                                            'ui.employees.show.documents_pending',
                                        )}
                                    </CardContent>
                                </Card>
                            </Deferred>
                        </TabsContent>
                    </Tabs>
                </div>
            </div>
        </>
    );
}
