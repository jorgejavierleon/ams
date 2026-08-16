<?php

use App\Enums\OvertimeRequestStatus;
use App\Models\OvertimeAuthorization;
use App\Models\OvertimeRequest;
use App\Models\Workday;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The employee's ask under Mode A (PRD §7.1, KOL-45): a date and an
     * optional reason, submitted before the hours are worked. Deliberately not
     * the same row as {@see OvertimeAuthorization} — that record
     * requires an already-computed {@see Workday}, which does not
     * exist yet for a request made ahead of time. Approving a request is a
     * green light, not a payable hour: the eventual worked day still goes
     * through the authorisation record and a human decision there.
     *
     * @see OvertimeRequest
     */
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('requested_hours');
            $table->text('reason')->nullable();
            $table->string('status')->default(OvertimeRequestStatus::Pending->value);

            // Who decided and when, following the same reviewer shape as
            // `overtime_authorizations` and `mark_modifications`.
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_reason')->nullable();

            $table->timestamps();

            // The supervisor's queue reads a tenant's pending requests.
            $table->index(['organization_id', 'status']);
            // One employee's own request history.
            $table->index(['user_id', 'date']);
        });
    }
};
