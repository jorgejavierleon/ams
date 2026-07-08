import { Link, useForm } from '@inertiajs/react';
import {
    Briefcase,
    Phone,
    Settings as SettingsIcon,
    ShieldCheck,
    User as UserIcon,
} from 'lucide-react';
import { useState  } from 'react';
import type {FormEvent} from 'react';
import AlertError from '@/components/alert-error';
import { Combobox } from '@/components/combobox';
import type { ComboboxOption } from '@/components/combobox';
import { FormField } from '@/components/form-field';
import {
    Avatar,
    AvatarFallback,
    AvatarImage,
} from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTranslations } from '@/hooks/use-translations';
import { index } from '@/routes/employees';

export type EmployeeFormOptions = {
    companies: ComboboxOption[];
    premises: ComboboxOption[];
    positions: ComboboxOption[];
    supervisors: ComboboxOption[];
    timezones: ComboboxOption[];
};

export type EmployeeFormData = {
    first_name: string;
    last_name: string;
    second_last_name: string;
    email: string;
    personal_email: string;
    password: string;
    rut: string;
    nationality: string;
    gender: string;
    is_active: boolean;
    company_id: string;
    premise_id: string;
    position_id: string;
    supervisor_id: string;
    contract_start_date: string;
    contract_end_date: string;
    is_admin: boolean;
    vacation_days: string;
    additional_vacation_days: string;
    administrative_days: string;
    has_additional_sundays: boolean;
    phone: string;
    emergency_contact_name: string;
    emergency_contact_phone: string;
    timezone: string;
    avatar: File | null;
};

type Props = {
    method: 'post' | 'patch';
    action: string;
    submitLabel: string;
    options: EmployeeFormOptions;
    initial: EmployeeFormData;
    /** Existing avatar URL to preview in edit mode. */
    currentAvatar?: string | null;
};

/**
 * The five-tab employee form shared by the create and edit pages. Avatar
 * uploads force a multipart request; edits spoof the PATCH verb via `_method`
 * so the file part still transmits.
 */
export default function EmployeeForm({
    method,
    action,
    submitLabel,
    options,
    initial,
    currentAvatar = null,
}: Props) {
    const { t } = useTranslations();
    const { data, setData, post, processing, errors } = useForm<
        EmployeeFormData & { _method?: string }
    >({
        ...initial,
        ...(method === 'patch' ? { _method: 'patch' } : {}),
    });

    const fieldErrors = errors as Record<string, string>;
    const [avatarPreview, setAvatarPreview] = useState<string | null>(
        currentAvatar,
    );

    const noneOption: ComboboxOption = {
        value: '',
        label: t('ui.employees.form.none'),
    };

    function submit(event: FormEvent) {
        event.preventDefault();
        post(action, { forceFormData: true, preserveScroll: true });
    }

    function onAvatarChange(file: File | null) {
        setData('avatar', file);
        setAvatarPreview(file ? URL.createObjectURL(file) : currentAvatar);
    }

    const hasErrors = Object.keys(errors).length > 0;

    return (
        <form onSubmit={submit} noValidate className="grid max-w-4xl gap-6">
            {hasErrors && (
                <AlertError
                    title={t('ui.employees.form.has_errors')}
                    errors={Object.values(fieldErrors)}
                />
            )}

            <Tabs defaultValue="personal">
                <TabsList>
                    <TabsTrigger value="personal">
                        <UserIcon className="size-4" />
                        {t('ui.employees.tabs.personal')}
                    </TabsTrigger>
                    <TabsTrigger value="labor">
                        <Briefcase className="size-4" />
                        {t('ui.employees.tabs.labor')}
                    </TabsTrigger>
                    <TabsTrigger value="admin">
                        <ShieldCheck className="size-4" />
                        {t('ui.employees.tabs.admin')}
                    </TabsTrigger>
                    <TabsTrigger value="contact">
                        <Phone className="size-4" />
                        {t('ui.employees.tabs.contact')}
                    </TabsTrigger>
                    <TabsTrigger value="system">
                        <SettingsIcon className="size-4" />
                        {t('ui.employees.tabs.system')}
                    </TabsTrigger>
                </TabsList>

                {/* Personal */}
                <TabsContent value="personal" className="grid gap-6 pt-2">
                    <div className="flex items-center gap-4">
                        <Avatar className="size-16">
                            {avatarPreview ? (
                                <AvatarImage src={avatarPreview} alt="" />
                            ) : null}
                            <AvatarFallback>
                                {data.first_name.charAt(0).toUpperCase() || '?'}
                            </AvatarFallback>
                        </Avatar>
                        <div className="grid gap-2">
                            <Label htmlFor="avatar">
                                {t('ui.employees.form.avatar')}
                            </Label>
                            <Input
                                id="avatar"
                                type="file"
                                accept="image/*"
                                className="max-w-xs"
                                onChange={(e) =>
                                    onAvatarChange(e.target.files?.[0] ?? null)
                                }
                            />
                            {fieldErrors.avatar && (
                                <p className="text-sm text-red-600 dark:text-red-400">
                                    {fieldErrors.avatar}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="is_active"
                            checked={data.is_active}
                            onCheckedChange={(checked) =>
                                setData('is_active', checked === true)
                            }
                        />
                        <Label htmlFor="is_active">
                            {t('ui.employees.form.is_active')}
                        </Label>
                    </div>

                    <div className="grid gap-6 sm:grid-cols-2">
                        <FormField
                            label={t('ui.employees.form.first_name')}
                            htmlFor="first_name"
                            required
                            error={fieldErrors.first_name}
                        >
                            <Input
                                id="first_name"
                                value={data.first_name}
                                onChange={(e) =>
                                    setData('first_name', e.target.value)
                                }
                                autoFocus
                            />
                        </FormField>

                        <FormField
                            label={t('ui.employees.form.last_name')}
                            htmlFor="last_name"
                            required
                            error={fieldErrors.last_name}
                        >
                            <Input
                                id="last_name"
                                value={data.last_name}
                                onChange={(e) =>
                                    setData('last_name', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label={t('ui.employees.form.second_last_name')}
                            htmlFor="second_last_name"
                            error={fieldErrors.second_last_name}
                        >
                            <Input
                                id="second_last_name"
                                value={data.second_last_name}
                                onChange={(e) =>
                                    setData('second_last_name', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label={t('ui.employees.form.rut')}
                            htmlFor="rut"
                            required
                            error={fieldErrors.rut}
                        >
                            <Input
                                id="rut"
                                value={data.rut}
                                onChange={(e) => setData('rut', e.target.value)}
                            />
                        </FormField>

                        <FormField
                            label={t('ui.employees.form.email')}
                            htmlFor="email"
                            required
                            error={fieldErrors.email}
                        >
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label={t('ui.employees.form.password')}
                            htmlFor="password"
                            required={method === 'post'}
                            error={fieldErrors.password}
                            hint={
                                method === 'patch'
                                    ? t('ui.employees.form.password_hint')
                                    : undefined
                            }
                        >
                            <Input
                                id="password"
                                type="password"
                                autoComplete="new-password"
                                value={data.password}
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label={t('ui.employees.form.nationality')}
                            htmlFor="nationality"
                            error={fieldErrors.nationality}
                        >
                            <Input
                                id="nationality"
                                value={data.nationality}
                                onChange={(e) =>
                                    setData('nationality', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label={t('ui.employees.form.gender')}
                            htmlFor="gender"
                            error={fieldErrors.gender}
                        >
                            <Input
                                id="gender"
                                value={data.gender}
                                onChange={(e) =>
                                    setData('gender', e.target.value)
                                }
                            />
                        </FormField>
                    </div>
                </TabsContent>

                {/* Labor / Organization */}
                <TabsContent value="labor" className="grid gap-6 pt-2">
                    <div className="grid gap-6 sm:grid-cols-2">
                        <FormField
                            label={t('ui.employees.form.company')}
                            htmlFor="company_id"
                            required
                            error={fieldErrors.company_id}
                        >
                            <Combobox
                                id="company_id"
                                options={options.companies}
                                value={data.company_id}
                                onChange={(value) =>
                                    setData('company_id', value)
                                }
                                placeholder={t('ui.employees.form.select')}
                                searchPlaceholder={t('ui.employees.form.search')}
                                emptyLabel={t('ui.employees.form.no_results')}
                            />
                        </FormField>

                        <FormField
                            label={t('ui.employees.form.premise')}
                            htmlFor="premise_id"
                            error={fieldErrors.premise_id}
                        >
                            <Combobox
                                id="premise_id"
                                options={[noneOption, ...options.premises]}
                                value={data.premise_id}
                                onChange={(value) =>
                                    setData('premise_id', value)
                                }
                                placeholder={t('ui.employees.form.select')}
                                searchPlaceholder={t('ui.employees.form.search')}
                                emptyLabel={t('ui.employees.form.no_results')}
                            />
                        </FormField>

                        <FormField
                            label={t('ui.employees.form.position')}
                            htmlFor="position_id"
                            error={fieldErrors.position_id}
                        >
                            <Combobox
                                id="position_id"
                                options={[noneOption, ...options.positions]}
                                value={data.position_id}
                                onChange={(value) =>
                                    setData('position_id', value)
                                }
                                placeholder={t('ui.employees.form.select')}
                                searchPlaceholder={t('ui.employees.form.search')}
                                emptyLabel={t('ui.employees.form.no_results')}
                            />
                        </FormField>

                        <FormField
                            label={t('ui.employees.form.supervisor')}
                            htmlFor="supervisor_id"
                            error={fieldErrors.supervisor_id}
                        >
                            <Combobox
                                id="supervisor_id"
                                options={[noneOption, ...options.supervisors]}
                                value={data.supervisor_id}
                                onChange={(value) =>
                                    setData('supervisor_id', value)
                                }
                                placeholder={t('ui.employees.form.select')}
                                searchPlaceholder={t('ui.employees.form.search')}
                                emptyLabel={t('ui.employees.form.no_results')}
                            />
                        </FormField>

                        <FormField
                            label={t('ui.employees.form.contract_start_date')}
                            htmlFor="contract_start_date"
                            error={fieldErrors.contract_start_date}
                        >
                            <Input
                                id="contract_start_date"
                                type="date"
                                value={data.contract_start_date}
                                onChange={(e) =>
                                    setData(
                                        'contract_start_date',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>

                        <FormField
                            label={t('ui.employees.form.contract_end_date')}
                            htmlFor="contract_end_date"
                            error={fieldErrors.contract_end_date}
                        >
                            <Input
                                id="contract_end_date"
                                type="date"
                                value={data.contract_end_date}
                                onChange={(e) =>
                                    setData('contract_end_date', e.target.value)
                                }
                            />
                        </FormField>
                    </div>
                </TabsContent>

                {/* Admin / Benefits */}
                <TabsContent value="admin" className="grid gap-6 pt-2">
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="is_admin"
                            checked={data.is_admin}
                            onCheckedChange={(checked) =>
                                setData('is_admin', checked === true)
                            }
                        />
                        <Label htmlFor="is_admin">
                            {t('ui.employees.form.is_admin')}
                        </Label>
                    </div>

                    <div className="grid gap-6 sm:grid-cols-3">
                        <FormField
                            label={t('ui.employees.form.vacation_days')}
                            htmlFor="vacation_days"
                            error={fieldErrors.vacation_days}
                        >
                            <Input
                                id="vacation_days"
                                type="number"
                                min={0}
                                step="0.5"
                                value={data.vacation_days}
                                onChange={(e) =>
                                    setData('vacation_days', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label={t(
                                'ui.employees.form.additional_vacation_days',
                            )}
                            htmlFor="additional_vacation_days"
                            error={fieldErrors.additional_vacation_days}
                        >
                            <Input
                                id="additional_vacation_days"
                                type="number"
                                min={0}
                                step="0.5"
                                value={data.additional_vacation_days}
                                onChange={(e) =>
                                    setData(
                                        'additional_vacation_days',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>

                        <FormField
                            label={t('ui.employees.form.administrative_days')}
                            htmlFor="administrative_days"
                            error={fieldErrors.administrative_days}
                        >
                            <Input
                                id="administrative_days"
                                type="number"
                                min={0}
                                step="0.5"
                                value={data.administrative_days}
                                onChange={(e) =>
                                    setData(
                                        'administrative_days',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>
                    </div>

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="has_additional_sundays"
                            checked={data.has_additional_sundays}
                            onCheckedChange={(checked) =>
                                setData(
                                    'has_additional_sundays',
                                    checked === true,
                                )
                            }
                        />
                        <Label htmlFor="has_additional_sundays">
                            {t('ui.employees.form.has_additional_sundays')}
                        </Label>
                    </div>
                </TabsContent>

                {/* Contact */}
                <TabsContent value="contact" className="grid gap-6 pt-2">
                    <div className="grid gap-6 sm:grid-cols-2">
                        <FormField
                            label={t('ui.employees.form.personal_email')}
                            htmlFor="personal_email"
                            error={fieldErrors.personal_email}
                        >
                            <Input
                                id="personal_email"
                                type="email"
                                value={data.personal_email}
                                onChange={(e) =>
                                    setData('personal_email', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label={t('ui.employees.form.phone')}
                            htmlFor="phone"
                            error={fieldErrors.phone}
                        >
                            <Input
                                id="phone"
                                value={data.phone}
                                onChange={(e) =>
                                    setData('phone', e.target.value)
                                }
                            />
                        </FormField>

                        <FormField
                            label={t('ui.employees.form.emergency_contact_name')}
                            htmlFor="emergency_contact_name"
                            error={fieldErrors.emergency_contact_name}
                        >
                            <Input
                                id="emergency_contact_name"
                                value={data.emergency_contact_name}
                                onChange={(e) =>
                                    setData(
                                        'emergency_contact_name',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>

                        <FormField
                            label={t(
                                'ui.employees.form.emergency_contact_phone',
                            )}
                            htmlFor="emergency_contact_phone"
                            error={fieldErrors.emergency_contact_phone}
                        >
                            <Input
                                id="emergency_contact_phone"
                                value={data.emergency_contact_phone}
                                onChange={(e) =>
                                    setData(
                                        'emergency_contact_phone',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>
                    </div>
                </TabsContent>

                {/* System */}
                <TabsContent value="system" className="grid gap-6 pt-2">
                    <div className="grid gap-6 sm:grid-cols-2">
                        <FormField
                            label={t('ui.employees.form.timezone')}
                            htmlFor="timezone"
                            required
                            error={fieldErrors.timezone}
                        >
                            <Combobox
                                id="timezone"
                                options={options.timezones}
                                value={data.timezone}
                                onChange={(value) => setData('timezone', value)}
                                placeholder={t('ui.employees.form.select')}
                                searchPlaceholder={t('ui.employees.form.search')}
                                emptyLabel={t('ui.employees.form.no_results')}
                            />
                        </FormField>
                    </div>
                </TabsContent>
            </Tabs>

            <div className="flex items-center gap-3">
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    {submitLabel}
                </Button>
                <Button variant="ghost" asChild>
                    <Link href={index()}>{t('ui.employees.form.cancel')}</Link>
                </Button>
            </div>
        </form>
    );
}
