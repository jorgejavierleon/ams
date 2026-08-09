<?php

namespace App\Console\Commands;

use App\Jobs\CalculateOvertime as CalculateOvertimeJob;
use App\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Dispatches the overtime calculation engine — the nightly close-out pass by
 * default, a backfill when given a range.
 *
 * Both are the same job over the same idempotent write path, so re-running a
 * period that was already processed is safe by construction and corrects it
 * rather than duplicating it.
 */
#[Signature('overtime:calculate
    {--organization=* : Restrict to these organization ids (defaults to every organization)}
    {--from= : First date to process (defaults to yesterday)}
    {--to= : Last date to process, inclusive (defaults to --from)}
    {--sync : Run the calculation now instead of queueing it}')]
#[Description('Calculate overtime (OHC) for a date or date range, per organization (PRD §7.2).')]
class CalculateOvertime extends Command
{
    public function handle(): int
    {
        // Yesterday, because the close-out pass runs after the day it settles.
        $from = CarbonImmutable::parse($this->option('from') ?? 'yesterday')->startOfDay();
        $to = CarbonImmutable::parse($this->option('to') ?? $from)->startOfDay();

        if ($to->lessThan($from)) {
            $this->error('--to cannot be earlier than --from.');

            return self::FAILURE;
        }

        $organizationIds = $this->organizationIds();

        if ($organizationIds === []) {
            $this->warn('No organizations to process.');

            return self::SUCCESS;
        }

        foreach ($organizationIds as $organizationId) {
            $job = new CalculateOvertimeJob($organizationId, $from, $to);

            $this->option('sync') ? dispatch_sync($job) : dispatch($job);
        }

        $this->info(sprintf(
            'Overtime calculation %s for %d organization(s) over %s → %s.',
            $this->option('sync') ? 'completed' : 'queued',
            count($organizationIds),
            $from->toDateString(),
            $to->toDateString(),
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function organizationIds(): array
    {
        /** @var array<int, string> $requested */
        $requested = $this->option('organization');

        $query = Organization::withoutGlobalScopes();

        if ($requested !== []) {
            $query->whereIn('id', array_map(intval(...), $requested));
        }

        return $query->pluck('id')->map(intval(...))->all();
    }
}
