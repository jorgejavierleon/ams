<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stamp each computed day with the legal-limit version it was judged
     * against. The stamp is not what drives the figures — those are resolved
     * from the day's own date at calculation time — it is the proof of which
     * rule was applied, and what makes a disagreement between the two
     * detectable instead of invisible.
     */
    public function up(): void
    {
        Schema::table('workdays', function (Blueprint $table) {
            // restrictOnDelete: a version a calculated day was judged against
            // can no longer be removed, which is half of the append-only
            // guarantee enforced by the database rather than by the model.
            $table->foreignId('legal_hour_limit_id')
                ->nullable()
                ->after('shift_id')
                ->constrained()
                ->restrictOnDelete();
        });

        $this->stampExistingWorkdays();
    }

    /**
     * Stamp the days computed before the column existed with the version their
     * own date resolves to. They were in fact judged against the ordinary week
     * of their date — the old constant simply happened to agree with it — so
     * this records what was applied rather than inventing it. Days predating
     * the first version keep a null stamp; there is no rule to claim for them.
     */
    private function stampExistingWorkdays(): void
    {
        $versions = DB::table('legal_hour_limits')->orderBy('effective_from')->get();

        foreach ($versions as $index => $version) {
            $next = $versions[$index + 1] ?? null;

            DB::table('workdays')
                ->where('date', '>=', $version->effective_from)
                ->when($next, fn ($query) => $query->where('date', '<', $next->effective_from))
                ->update(['legal_hour_limit_id' => $version->id]);
        }
    }

    public function down(): void
    {
        Schema::table('workdays', function (Blueprint $table) {
            $table->dropConstrainedForeignId('legal_hour_limit_id');
        });
    }
};
