<?php

namespace App\Observers;

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

        $mark->employee_rut = $user?->rut;
        $mark->employee_name = $user?->name;
        $mark->employer_rut = $user?->company?->rut;
        $mark->employer_name = $user?->company?->social_reason;

        $mark->checksum = hash(
            'sha256',
            $user?->id.$mark->type->value.$mark->date_time->toIso8601String(),
        );

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
