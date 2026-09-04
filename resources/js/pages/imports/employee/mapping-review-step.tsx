import { useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo } from 'react';
import { Combobox } from '@/components/combobox';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslations } from '@/hooks/use-translations';
import { update as updateMapping } from '@/routes/imports/mapping';

const IGNORE_VALUE = '__ignore__';

type MappingStatus = 'mapped' | 'unmapped' | 'ignored';

type SchemaField = {
    name: string;
    label: string;
    requiredForCreateOnly: boolean;
};

type MappingRow = {
    sourceColumnIndex: number;
    sourceHeaderLabel: string | null;
    targetField: string | null;
    status: MappingStatus;
};

type Props = {
    importRunId: number;
    originalFilename: string | null;
    columnMapping: MappingRow[];
    schemaFields: SchemaField[];
    onSaved: () => void;
};

/**
 * The Employee import wizard's mapping-review step (KOL-99): a flat table,
 * one row per uploaded column, each with an inline searchable Combobox
 * listing every schema field plus an explicit "Ignore this column" option.
 * Adapted from KOL-94.7's prototype Variant A — binary threshold, no
 * confidence tiers surfaced.
 */
export function MappingReviewStep({
    importRunId,
    originalFilename,
    columnMapping,
    schemaFields,
    onSaved,
}: Props) {
    const { t } = useTranslations();

    const { data, setData, patch, processing, errors } = useForm<{
        mapping: MappingRow[];
    }>({ mapping: columnMapping });

    const options = useMemo(
        () => [
            {
                value: IGNORE_VALUE,
                label: t('ui.employees.import.mapping.ignore_option'),
            },
            ...schemaFields.map((field) => ({
                value: field.name,
                label: field.label,
            })),
        ],
        [schemaFields, t],
    );

    const fieldsByName = useMemo(
        () => new Map(schemaFields.map((field) => [field.name, field])),
        [schemaFields],
    );

    const mappedTargets = new Set(
        data.mapping
            .filter((row) => row.status === 'mapped')
            .map((row) => row.targetField),
    );
    const missingRequired = schemaFields.filter(
        (field) => field.requiredForCreateOnly && !mappedTargets.has(field.name),
    );
    const mappedCount = data.mapping.filter(
        (row) => row.status === 'mapped',
    ).length;
    const unmappedCount = data.mapping.filter(
        (row) => row.status === 'unmapped',
    ).length;

    function setRow(
        index: number,
        next: Pick<MappingRow, 'targetField' | 'status'>,
    ) {
        setData(
            'mapping',
            data.mapping.map((row, i) => (i === index ? { ...row, ...next } : row)),
        );
    }

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        patch(updateMapping(importRunId).url, {
            preserveScroll: true,
            onSuccess: onSaved,
        });
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-1">
                <p className="text-sm text-muted-foreground">
                    {t('ui.employees.import.mapping.description', {
                        count: data.mapping.length,
                        filename: originalFilename ?? '',
                    })}
                </p>
                <div
                    className={
                        unmappedCount > 0
                            ? 'flex items-center gap-1.5 text-sm font-medium text-amber-600 dark:text-amber-500'
                            : 'flex items-center gap-1.5 text-sm text-muted-foreground'
                    }
                >
                    {unmappedCount > 0 ? (
                        <AlertTriangle className="size-4 shrink-0" />
                    ) : (
                        <CheckCircle2 className="size-4 shrink-0 text-green-600 dark:text-green-500" />
                    )}
                    <span>
                        {unmappedCount > 0
                            ? t('ui.employees.import.mapping.summary_needs_review', {
                                  mapped: mappedCount,
                                  total: data.mapping.length,
                                  unmapped: unmappedCount,
                              })
                            : t('ui.employees.import.mapping.summary_reviewed', {
                                  total: data.mapping.length,
                              })}
                    </span>
                </div>
            </div>

            {missingRequired.length > 0 && (
                <Alert variant="destructive">
                    <AlertTriangle className="size-4" />
                    <AlertTitle>
                        {t('ui.employees.import.mapping.required_missing_title')}
                    </AlertTitle>
                    <AlertDescription>
                        {t(
                            'ui.employees.import.mapping.required_missing_description',
                            { fields: missingRequired.map((f) => f.label).join(', ') },
                        )}
                    </AlertDescription>
                </Alert>
            )}

            {errors.mapping && (
                <Alert variant="destructive">
                    <AlertDescription>{errors.mapping}</AlertDescription>
                </Alert>
            )}

            <Card>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    {t('ui.employees.import.mapping.column_header')}
                                </TableHead>
                                <TableHead>
                                    {t('ui.employees.import.mapping.target_header')}
                                </TableHead>
                                <TableHead>
                                    {t('ui.employees.import.mapping.status_header')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {data.mapping.map((row, index) => {
                                const field = row.targetField
                                    ? fieldsByName.get(row.targetField)
                                    : null;

                                return (
                                    <TableRow key={row.sourceColumnIndex}>
                                        <TableCell className="font-medium">
                                            {row.sourceHeaderLabel?.trim() || (
                                                <span className="text-muted-foreground italic">
                                                    {t(
                                                        'ui.employees.import.mapping.blank_header',
                                                    )}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="w-72">
                                            <Combobox
                                                options={options}
                                                value={
                                                    row.status === 'ignored'
                                                        ? IGNORE_VALUE
                                                        : (row.targetField ?? '')
                                                }
                                                onChange={(value) => {
                                                    if (value === IGNORE_VALUE) {
                                                        setRow(index, {
                                                            targetField: null,
                                                            status: 'ignored',
                                                        });

                                                        return;
                                                    }

                                                    setRow(index, {
                                                        targetField: value,
                                                        status: 'mapped',
                                                    });
                                                }}
                                                placeholder={t(
                                                    'ui.employees.import.mapping.select_placeholder',
                                                )}
                                                searchPlaceholder={t(
                                                    'ui.employees.import.mapping.search_placeholder',
                                                )}
                                                emptyLabel={t(
                                                    'ui.employees.import.mapping.empty_results',
                                                )}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            {row.status === 'mapped' && field && (
                                                <Badge
                                                    variant="secondary"
                                                    className="text-green-700 dark:text-green-400"
                                                >
                                                    {t(
                                                        'ui.employees.import.mapping.status_mapped',
                                                    )}
                                                </Badge>
                                            )}
                                            {row.status === 'ignored' && (
                                                <Badge variant="outline">
                                                    {t(
                                                        'ui.employees.import.mapping.status_ignored',
                                                    )}
                                                </Badge>
                                            )}
                                            {row.status === 'unmapped' && (
                                                <Badge
                                                    variant="outline"
                                                    className="gap-1 border-amber-500/50 text-amber-600 dark:text-amber-400"
                                                >
                                                    <AlertTriangle className="size-3" />
                                                    {t(
                                                        'ui.employees.import.mapping.status_unmapped',
                                                    )}
                                                </Badge>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <div className="flex justify-end">
                <Button
                    type="submit"
                    disabled={missingRequired.length > 0 || processing}
                >
                    {t('ui.employees.import.mapping.submit_and_continue')}
                </Button>
            </div>
        </form>
    );
}
