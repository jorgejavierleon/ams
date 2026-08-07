<?php

namespace App\Observers;

use App\Http\Controllers\Dt\MarkValidationController;
use App\Mail\MarkCreated;
use App\Models\Mark;
use App\Support\Folio;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class MarkObserver
{
    /**
     * Stamp the immutable legal snapshot, integrity checksum and receipt folio
     * onto the mark before it is persisted. The employer identity comes from the
     * employee's company (the contracting legal entity); the premise from the
     * mark or the employee's assigned premise.
     */
    public function creating(Mark $mark): void
    {
        $user = $mark->user;

        $premise = $mark->premise ?? $user?->premise;
        if ($premise !== null) {
            $mark->premise()->associate($premise);
            $mark->premise_name = $premise->name;
            $mark->premise_address = $premise->address;
        }

        if ($mark->company_id === null) {
            $mark->company_id = $user?->company_id;
        }

        if ($mark->getAttribute('date_time') === null) {
            $mark->date_time = Carbon::now();
        }
        if ($mark->original_date_time === null) {
            $mark->original_date_time = $mark->date_time;
        }

        // When the register received the punch. On anything but a queued punch
        // that is the same instant the mark is stamped, so the two agree by
        // construction; on a queued one the caller has already set it to the
        // moment the sync arrived, and the gap to `date_time` is the queue's
        // own age (Res. 38 Art. 10).
        if ($mark->synced_at === null) {
            $mark->synced_at = $mark->date_time;
        }

        $mark->employee_rut = $user?->rut;
        $mark->employee_name = $user?->name;
        $mark->employer_rut = $user?->company?->rut;
        $mark->employer_name = $user?->company?->social_reason;

        $mark->checksum = hash('sha256', $this->checksumInput($mark));

        // Allocated beside the checksum, and for the same reason: a mark that
        // reached the register without a receipt number is one no employee can
        // quote back (Art. 13). `organization_id` is already stamped here —
        // BelongsToOrganization boots as a trait, and trait listeners run before
        // the ones an #[ObservedBy] attribute registers.
        if ($mark->folio === null) {
            $mark->folio = Folio::allocate($mark->organization_id, $mark->date_time);
        }
    }

    /**
     * The input the Art. 8 checksum is computed over: who punched, which way,
     * and when — plus, on a queued punch only, where that "when" came from.
     *
     * **The conditional is the decision, and it is deliberate** (KOL-54).
     * Art. 8 asks for a hash `de los datos de cada operación`, and on a queued
     * punch the provenance *is* part of the operation: `date_time` is adjudicated
     * from the device reading rather than read off the server's clock, so a
     * `captured_offline` that could be cleared, or a `device_datetime` that could
     * be rewritten, without breaking the hash would leave the register unable to
     * say how its own timestamp was obtained (Art. 10 second paragraph needs
     * exactly that to justify the exception). Both are therefore inside the
     * envelope, and clearing either invalidates the checksum.
     *
     * Appending it only when the punch was queued is what keeps every mark
     * recorded before this change verifiable: an online punch — and every row
     * already in the register, which is all of them — hashes over exactly the
     * string it always did, so no stored checksum moves and no proof already
     * printed stops matching. The cost is one branch in any Art. 8 verification
     * tool, and this codebase's own ({@see MarkValidationController})
     * pays none of it: an inspector's checksum is looked up, never recomputed.
     * Unconditional inclusion would have invalidated the whole existing register
     * to spare that branch, which is not a trade Art. 8 permits.
     *
     * Geolocation stays outside, as KOL-34 left it, and the distinction holds:
     * a coordinate is a measurement *about* the punch, legitimately absent, and
     * attached after the fact — the provenance of the legal timestamp is not.
     */
    private function checksumInput(Mark $mark): string
    {
        $input = $mark->user?->id.$mark->type->value.$mark->date_time->toIso8601String();

        if (! $mark->captured_offline) {
            return $input;
        }

        return $input.'|offline|'.$mark->device_datetime?->toIso8601String();
    }

    /**
     * Email the employee a receipt of their punch, when they have a personal
     * address on file.
     */
    public function created(Mark $mark): void
    {
        if ($mark->user?->personal_email === null) {
            return;
        }

        Mail::to($mark->user->personal_email)->send(new MarkCreated($mark));
    }
}
