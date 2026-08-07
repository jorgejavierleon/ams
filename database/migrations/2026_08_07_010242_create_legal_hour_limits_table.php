<?php

use App\Services\LegalHourLimits;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The date-versioned legal working-hour limits of Chile.
     *
     * Deliberately global: no `organization_id`. These values are the law, the
     * same for every employer in the country, and a tenant able to edit them is
     * a tenant able to raise their own overtime ceiling and have the audit
     * endorse the result. Maintenance happens in the SaaS panel; tenant code
     * only reads, through {@see LegalHourLimits}.
     *
     * Rows are append-only. A new law adds a version with its own effective
     * date; the previous row is never rewritten, because rewriting it would
     * change what an already-closed period reports.
     */
    public function up(): void
    {
        Schema::create('legal_hour_limits', function (Blueprint $table) {
            $table->id();
            // The date this version starts applying. Unique because two
            // versions in force on the same day has no meaning, and the
            // resolver picks the latest row at or before a given date.
            $table->date('effective_from')->unique();
            // Ordinary jornada (Código del Trabajo arts. 22 & 28).
            $table->decimal('ordinary_weekly_hours', 5, 2);
            $table->decimal('ordinary_daily_hours', 5, 2);
            // Overtime on top of the ordinary jornada (art. 31).
            $table->decimal('max_overtime_daily_hours', 5, 2);
            $table->decimal('max_overtime_weekly_hours', 5, 2);
            // The ceiling on ordinary plus extraordinary combined, which is not
            // simply the sum of the two above: the daily ceiling is a hard 12h.
            $table->decimal('max_total_daily_hours', 5, 2);
            $table->decimal('max_total_weekly_hours', 5, 2);
            $table->string('legal_reference');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::table('legal_hour_limits')->insert($this->baseline());
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_hour_limits');
    }

    /**
     * The real timeline of the Chilean ordinary workweek, seeded here rather
     * than in a seeder because the application cannot resolve a limit for any
     * date without it — every environment and every test database needs these
     * rows to exist, not just the ones that ran `db:seed`.
     *
     * Ley 21.561 was published on 26 April 2023 and reduces the week in three
     * steps rather than one, at one, three and five years from publication.
     * The daily caps are untouched by it: the ordinary day stays at 10h
     * (art. 28), overtime at 2h/day (art. 31), and the combined day at 12h.
     *
     * @return list<array<string, mixed>>
     */
    private function baseline(): array
    {
        $now = now();

        $rows = [
            ['2005-01-01', 45, 'Ley 19.759', 'Ordinary week reduced from 48 to 45 hours, in force from 1 January 2005.'],
            ['2024-04-26', 44, 'Ley 21.561', 'First step of the reduction to 40 hours: 44 hours one year after publication.'],
            ['2026-04-26', 42, 'Ley 21.561', 'Second step: 42 hours three years after publication.'],
            ['2028-04-26', 40, 'Ley 21.561', 'Third and final step: 40 hours five years after publication.'],
        ];

        return array_map(fn (array $row): array => [
            'effective_from' => $row[0],
            'ordinary_weekly_hours' => $row[1],
            'ordinary_daily_hours' => 10,
            'max_overtime_daily_hours' => 2,
            'max_overtime_weekly_hours' => 12,
            'max_total_daily_hours' => 12,
            'max_total_weekly_hours' => $row[1] + 12,
            'legal_reference' => $row[2],
            'notes' => $row[3],
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);
    }
};
