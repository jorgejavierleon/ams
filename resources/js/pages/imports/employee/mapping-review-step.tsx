import { useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
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
        patch(updateMapping(importRunId).url, { preserveScroll: true });
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
                <p className="text-sm text-muted-foreground">
                    {t('ui.employees.import.mapping.summary', {
                        mapped: mappedCount,
                        total: data.mapping.length,
                    })}
                </p>
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
                                                value={row.targetField ?? ''}
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
                                                <Badge variant="destructive">
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

            <Button
                type="submit"
                disabled={missingRequired.length > 0 || processing}
            >
                {t('ui.employees.import.mapping.submit')}
            </Button>
        </form>
    );
}
