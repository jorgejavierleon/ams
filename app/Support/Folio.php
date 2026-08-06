<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The receipt number an employee reads back to HR over the phone.
 *
 * Resolución 38 Art. 13 lists what a punch receipt must show; the mobile
 * receipt adds `N° comprobante` on top of it, and kolvi-mobile decision D-F2-a
 * settled that it is a real number rather than a dressed-up `mark_id`: short,
 * stable, unambiguous when spoken aloud, and consistent with the register
 * (Art. 20a).
 *
 * The form is `YYYYMMDD-NNNN` — the day of the punch, then that day's sequence
 * within the organization. Two organizations issue the same folio on the same
 * day, which is why uniqueness is per organization and never global.
 */
final class Folio
{
    /**
     * `20260805-0042`. The sequence widens past four digits rather than wrap,
     * so an organization punching more than 9,999 times in a day keeps ordering
     * and uniqueness (`…-10000` sorts and reads unambiguously).
     */
    public const PATTERN = '/^\d{8}-\d{4,}$/';

    /**
     * Take the next folio for the organization on the date of the punch.
     *
     * The counter row is created if it is missing and then incremented under a
     * row lock, so two employees punching in the same second — a shift change,
     * which is exactly when punches arrive together — queue for consecutive
     * numbers instead of both reading the same one. A read-then-increment in
     * PHP would hand out the same folio twice; `marks`' unique index on
     * (organization_id, folio) is the floor underneath this.
     *
     * Marks carrying no organization key on 0, which is why `mark_folios` holds
     * a plain integer rather than a constrained foreign key.
     */
    public static function allocate(?int $organizationId, CarbonInterface $date): string
    {
        $organizationId ??= 0;
        $folioDate = $date->format('Y-m-d');

        // Portable "create it if nobody has yet": whichever request loses the
        // race is ignored rather than failing, and both go on to lock the row
        // that now certainly exists.
        DB::table('mark_folios')->insertOrIgnore([
            'organization_id' => $organizationId,
            'folio_date' => $folioDate,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $number = DB::transaction(function () use ($organizationId, $folioDate): int {
            $counter = DB::table('mark_folios')
                ->where('organization_id', $organizationId)
                ->where('folio_date', $folioDate)
                ->lockForUpdate()
                ->first();

            if ($counter === null) {
                throw new RuntimeException("The folio counter for organization {$organizationId} on {$folioDate} disappeared mid-allocation.");
            }

            $next = (int) $counter->last_number + 1;

            DB::table('mark_folios')
                ->where('id', $counter->id)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return $next;
        });

        return self::format($date, $number);
    }

    /**
     * Compose a folio from a date and a sequence number.
     */
    public static function format(CarbonInterface $date, int $number): string
    {
        return $date->format('Ymd').'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
