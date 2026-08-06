<?php

use App\Support\Folio;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            // The receipt number shown as `N° comprobante` (Res. 38 Art. 13),
            // `YYYYMMDD-NNNN`. Nullable only long enough to backfill.
            $table->string('folio', 20)->nullable()->after('checksum');
        });

        $this->backfillExistingMarks();

        Schema::table('marks', function (Blueprint $table) {
            // A mark without a receipt number is a receipt Art. 13 does not
            // cover, so the register may not hold one.
            $table->string('folio', 20)->nullable(false)->change();
            // Per organization, never global: two organizations legitimately
            // issue `20260805-0001` on the same day.
            $table->unique(['organization_id', 'folio']);
        });
    }

    /**
     * Give every mark already in the register a folio, numbered per
     * organization per day in the order the punches were made — date first,
     * then id to settle punches sharing a second. Soft-deleted marks are
     * numbered too: they are still rows of the register, and skipping them
     * would renumber everything after them.
     *
     * The counters are seeded from the result so the first punch after this
     * migration continues the day's sequence rather than colliding with it.
     */
    private function backfillExistingMarks(): void
    {
        /** @var array<string, array{organization_id: int, folio_date: string, last_number: int}> $counters */
        $counters = [];
        $pending = [];

        $marks = DB::table('marks')
            ->select('id', 'organization_id', 'date_time')
            ->orderBy('organization_id')
            ->orderBy('date_time')
            ->orderBy('id')
            ->cursor();

        foreach ($marks as $mark) {
            $date = Carbon::parse($mark->date_time);
            $organizationId = (int) ($mark->organization_id ?? 0);
            $folioDate = $date->format('Y-m-d');
            $key = $organizationId.'|'.$folioDate;

            $counters[$key] ??= [
                'organization_id' => $organizationId,
                'folio_date' => $folioDate,
                'last_number' => 0,
            ];

            $counters[$key]['last_number']++;

            $pending[] = [
                'id' => $mark->id,
                'folio' => Folio::format($date, $counters[$key]['last_number']),
            ];

            if (count($pending) >= 500) {
                $this->applyFolios($pending);
                $pending = [];
            }
        }

        $this->applyFolios($pending);

        foreach (array_chunk($counters, 500) as $chunk) {
            DB::table('mark_folios')->insert(array_map(fn (array $counter) => [
                ...$counter,
                'created_at' => now(),
                'updated_at' => now(),
            ], $chunk));
        }
    }

    /**
     * @param  list<array{id: int, folio: string}>  $pending
     */
    private function applyFolios(array $pending): void
    {
        if ($pending === []) {
            return;
        }

        DB::transaction(function () use ($pending) {
            foreach ($pending as $assignment) {
                DB::table('marks')
                    ->where('id', $assignment['id'])
                    ->update(['folio' => $assignment['folio']]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('marks', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'folio']);
            $table->dropColumn('folio');
        });
    }
};
