import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useMemo } from 'react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardTitle,
} from '@/components/ui/card';
import { useTranslations } from '@/hooks/use-translations';
import { cn } from '@/lib/utils';
import { update as updateStrategy } from '@/routes/imports/strategy';

type ImportStrategy = 'create_only' | 'update_only' | 'create_and_update';

type SchemaField = {
    name: string;
    label: string;
    requiredForCreateOnly: boolean;
    isMatchKeyEligible: boolean;
};

type Props = {
    importRunId: number;
    strategy: ImportStrategy | null;
    matchKey: string | null;
    schemaFields: SchemaField[];
    onBack: () => void;
    onSaved: () => void;
};

const STRATEGIES: ImportStrategy[] = [
    'create_only',
    'update_only',
    'create_and_update',
];

const STRATEGIES_NEEDING_MATCH_KEY: ImportStrategy[] = [
    'update_only',
    'create_and_update',
];

/**
 * The Employee import wizard's strategy step (KOL-100): a client-only
 * sub-step of the mapping_review status (there is no separate ImportRun
 * status for it — the run stays MappingReview until preview runs, KOL-101),
 * so navigation between it and MappingReviewStep is local React state, not
 * a route. Card-picker + match-key buttons shape matches KOL-94.9's
 * prototype (prototype/kol-94-9-import-wizard, step-strategy.tsx).
 */
export function StrategyStep({
    importRunId,
    strategy,
    matchKey,
    schemaFields,
    onBack,
    onSaved,
}: Props) {
    const { t } = useTranslations();

    const { data, setData, patch, processing, errors, recentlySuccessful } =
        useForm<{
            strategy: ImportStrategy | null;
            match_key: string | null;
        }>({ strategy, match_key: matchKey });

    const matchKeyFields = useMemo(
        () => schemaFields.filter((field) => field.isMatchKeyEligible),
        [schemaFields],
    );

    const needsMatchKey = data.strategy
        ? STRATEGIES_NEEDING_MATCH_KEY.includes(data.strategy)
        : false;
    const canSave =
        data.strategy !== null && (!needsMatchKey || data.match_key !== null);

    function selectStrategy(next: ImportStrategy) {
        setData({
            strategy: next,
            match_key: STRATEGIES_NEEDING_MATCH_KEY.includes(next)
                ? data.match_key
                : null,
        });
    }

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        patch(updateStrategy(importRunId).url, {
            preserveScroll: true,
            onSuccess: onSaved,
        });
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div>
                <p className="mb-3 text-sm font-medium">
                    {t('ui.employees.import.strategy.strategy_label')}
                </p>
                <div className="grid gap-3 sm:grid-cols-3">
                    {STRATEGIES.map((option) => (
                        <button
                            key={option}
                            type="button"
                            aria-pressed={data.strategy === option}
                            onClick={() => selectStrategy(option)}
                            className="text-left"
                        >
                            <Card
                                className={cn(
                                    'h-full cursor-pointer py-4 transition-colors hover:bg-accent',
                                    data.strategy === option &&
                                        'border-primary ring-1 ring-primary',
                                )}
                            >
                                <CardContent className="space-y-1.5">
                                    <CardTitle className="text-sm">
                                        {t(
                                            `ui.employees.import.strategy.options.${option}.title`,
                                        )}
                                    </CardTitle>
                                    <CardDescription>
                                        {t(
                                            `ui.employees.import.strategy.options.${option}.description`,
                                        )}
                                    </CardDescription>
                                </CardContent>
                            </Card>
                        </button>
                    ))}
                </div>
                {errors.strategy && (
                    <p className="mt-2 text-sm text-destructive">
                        {errors.strategy}
                    </p>
                )}
            </div>

            {needsMatchKey && (
                <div>
                    <p className="mb-3 text-sm font-medium">
                        {t('ui.employees.import.strategy.match_key_label')}
                    </p>
                    <div className="flex gap-3">
                        {matchKeyFields.map((field) => (
                            <button
                                key={field.name}
                                type="button"
                                aria-pressed={data.match_key === field.name}
                                onClick={() =>
                                    setData('match_key', field.name)
                                }
                                className={cn(
                                    'rounded-md border px-4 py-2 text-sm transition-colors hover:bg-accent',
                                    data.match_key === field.name &&
                                        'border-primary bg-primary text-primary-foreground hover:bg-primary/90',
                                )}
                            >
                                {field.label}
                            </button>
                        ))}
                    </div>
                    {errors.match_key && (
                        <p className="mt-2 text-sm text-destructive">
                            {errors.match_key}
                        </p>
                    )}
                </div>
            )}

            <div className="flex items-center justify-between">
                <Button type="button" variant="outline" onClick={onBack}>
                    {t('ui.employees.import.strategy.back')}
                </Button>
                <div className="flex items-center gap-3">
                    {recentlySuccessful && (
                        <span className="text-sm text-muted-foreground">
                            {t('ui.employees.import.strategy.saved')}
                        </span>
                    )}
                    <Button type="submit" disabled={!canSave || processing}>
                        {t('ui.employees.import.strategy.submit')}
                    </Button>
                </div>
            </div>
        </form>
    );
}
