<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTableSort;
use App\Enums\MarkModificationReason;
use App\Enums\MarkType;
use App\Enums\OvertimeAuthorizationStatus;
use App\Enums\OvertimeCompensationType;
use App\Enums\WorkdayStatus;
use App\Exceptions\OvertimeDecisionRefused;
use App\Managers\MarkModificationManager;
use App\Models\Company;
use App\Models\MarkModification;
use App\Models\OvertimeAuthorization;
use App\Models\Position;
use App\Models\Premise;
use App\Models\User;
use App\Models\Workday;
use App\Services\BusinessDayResolver;
use App\Services\WorkdayPresenter;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WorkdayController extends Controller
{
    use ResolvesTableSort;

    /**
     * The primary daily-operations screen: each employee's computed attendance
     * for the selected date range, filterable by employee, status, position and
     * premise, with pending mark-modification requests flagged.
     */
    public function index(Request $request): Response
    {
        abort_unless(Gate::any(['viewAny', 'viewTeam'], Workday::class), 403);

        // KOL-71: general (ViewAny:Workday) sees the whole organization; a
        // supervisor holding only the team permission (ViewTeam:Workday) is
        // scoped to their own direct reports, exactly like the overtime
        // queue it replaced.
        $isGeneral = $request->user()->can('viewAny', Workday::class);
        $supervisorId = $isGeneral ? null : $request->user()->id;
        $canDecide = $isGeneral || $request->user()->can('ApproveTeam:Workday');

        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['date', 'mark_in_at', 'mark_out_at', 'worked_time'],
            'date',
            'desc',
        );

        // The screen is date-first: default to today when no range is given.
        $from = $request->date('from') ?? Carbon::today();
        $to = $request->date('to') ?? Carbon::today();

        if ($to->lessThan($from)) {
            [$from, $to] = [$to, $from];
        }

        $statuses = $this->enumListFilter($request, 'statuses', WorkdayStatus::class);
        $employeeIds = $this->idListFilter($request, 'employees');
        $positionIds = $this->idListFilter($request, 'positions');
        $premiseIds = $this->idListFilter($request, 'premises');
        $overtimeStatuses = $this->enumListFilter($request, 'overtime_statuses', OvertimeAuthorizationStatus::class);

        $workdays = Workday::query()
            ->with([
                'user:id,name,position_id,supervisor_id,overtime_rest_day_eligible',
                'shift:id,name',
                'leave:id,type',
                'overtimeAuthorization.user:id,supervisor_id',
            ])
            ->withCount('pendingMarkModifications')
            ->betweenDates($from, $to)
            ->when($supervisorId, fn ($query) => $query->whereHas(
                'user',
                fn ($user) => $user->where('supervisor_id', $supervisorId),
            ))
            ->when($statuses, fn ($query) => $query->whereIn('status', $statuses))
            ->when($employeeIds, fn ($query) => $query->whereIn('user_id', $employeeIds))
            ->when($premiseIds, fn ($query) => $query->whereIn('premise_id', $premiseIds))
            ->when($positionIds, fn ($query) => $query->whereHas(
                'user',
                fn ($user) => $user->whereIn('position_id', $positionIds),
            ))
            ->when($overtimeStatuses, fn ($query) => $query->whereHas(
                'overtimeAuthorization',
                fn ($authorization) => $authorization->whereIn('status', $overtimeStatuses),
            ))
            ->orderBy($sort, $direction)
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('workdays/index', [
            'workdays' => $workdays->through(fn (Workday $workday) => [
                'id' => $workday->id,
                'employee' => $workday->user?->name,
                'date' => $workday->date->format('Y-m-d'),
                'status' => $workday->status?->value,
                'status_label' => $workday->status?->label(),
                'status_badge' => $workday->status?->badge(),
                'mark_in_at' => $workday->mark_in_at?->format('H:i'),
                'mark_out_at' => $workday->mark_out_at?->format('H:i'),
                'worked_time' => $this->trimSeconds($workday->worked_time),
                'in_time_difference' => $this->trimSeconds($workday->in_time_difference),
                'out_time_difference' => $this->trimSeconds($workday->out_time_difference),
                'shift' => $workday->shift?->name,
                'leave_type' => $workday->leave?->type->label(),
                'pending_modifications' => $workday->pending_mark_modifications_count,
                'overtime' => $this->overtimeRowData($workday),
            ]),
            'filters' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'statuses' => array_map(fn (WorkdayStatus $status) => $status->value, $statuses),
                'employees' => array_map('strval', $employeeIds),
                'positions' => array_map('strval', $positionIds),
                'premises' => array_map('strval', $premiseIds),
                'overtime_statuses' => array_map(fn (OvertimeAuthorizationStatus $status) => $status->value, $overtimeStatuses),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statusOptions' => WorkdayStatus::options(),
            'employeeOptions' => $this->employeeOptions(),
            'positionOptions' => $this->options(Position::query()),
            'premiseOptions' => $this->options(Premise::query()),
            'overtimeStatusOptions' => OvertimeAuthorizationStatus::options(),
            'reasonOptions' => MarkModificationReason::options(),
            'markTypeOptions' => MarkType::options(),
            'compensationTypeOptions' => OvertimeCompensationType::options(),
            'can' => [
                'decideOvertime' => $canDecide,
            ],
        ]);
    }

    /**
     * The index row's overtime summary (KOL-71), or null on a day with no
     * calculated overtime. A day the engine computed excess for but that has
     * no OvertimeAuthorization row (KOL-80: nothing creates one ahead of a
     * decision any more) is surfaced as `not_opened` rather than hidden —
     * visible and inert until someone approves it.
     *
     * @return array<string, mixed>|null
     */
    private function overtimeRowData(Workday $workday): ?array
    {
        if (! $workday->calculated_overtime || $workday->calculated_overtime === '00:00:00') {
            return null;
        }

        $authorization = $workday->overtimeAuthorization;
        $isApproved = $authorization?->isApproved() ?? false;

        return [
            'calculated_hours' => $workday->calculated_overtime,
            'authorized_hours' => $authorization?->authorized_hours,
            'final_hours' => $authorization?->final_hours,
            'status' => $authorization?->status->value ?? 'not_opened',
            'status_label' => $authorization?->status->label() ?? __('ui.workdays.overtime.statuses.not_opened'),
            'status_badge' => $authorization?->status->badge() ?? 'outline',
            'compensation_eligible' => $workday->user->overtime_rest_day_eligible,
            'can_decide' => ! $isApproved && Gate::allows('approve', $authorization ?? $this->provisionalAuthorization($workday)),
            'can_revoke' => $isApproved && Gate::allows('revoke', $authorization),
        ];
    }

    /**
     * A transient, unsaved OvertimeAuthorization for permission checks only.
     * KOL-80: a day nobody has acted on has no persisted row, so "may this
     * user decide it" cannot be answered by loading one. The policy only
     * reads `$authorization->user->supervisor_id`, so an in-memory instance
     * carrying that relation (already eager-loaded on the workday) answers
     * the same question without writing anything or an extra query.
     */
    private function provisionalAuthorization(Workday $workday): OvertimeAuthorization
    {
        return (new OvertimeAuthorization([
            'organization_id' => $workday->organization_id,
            'user_id' => $workday->user_id,
        ]))->setRelation('user', $workday->user);
    }

    /**
     * Open a pending mark-modification request against each selected workday.
     * The requests surface as pending indicators for HR to review and approve.
     */
    public function bulkModify(Request $request, MarkModificationManager $manager, BusinessDayResolver $businessDays): RedirectResponse
    {
        // Coarse pre-check only: a class-level Gate::authorize('update', ...)
        // cannot evaluate the per-row team scoping WorkdayPolicy::update()
        // needs (it requires a Workday instance), so the real check happens
        // per row below via Gate::allows(), exactly like the bulk overtime
        // action. Nobody without either permission ever reaches the filter.
        abort_unless(
            $request->user()->can('Update:Workday') || $request->user()->can('ApproveTeam:Workday'),
            403,
        );

        $organizationId = Company::currentOrganizationId();

        $data = $request->validate([
            'workdays' => ['required', 'array', 'min:1'],
            'workdays.*' => [
                'integer',
                Rule::exists('workdays', 'id')->where('organization_id', $organizationId),
            ],
            'mark_type' => ['required', Rule::enum(MarkType::class)],
            'time' => ['required', 'date_format:H:i'],
            'reason' => ['required', Rule::enum(MarkModificationReason::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Art. 41 c): drop workdays that cannot yet be corrected because the
        // following business day has not arrived, and (KOL-71) any a
        // supervisor is not authorised to act on — a general Update:Workday
        // holder passes every row, a team-scoped one only their own reports'.
        $workdays = Workday::query()
            ->whereIn('id', $data['workdays'])
            ->with('user:id,supervisor_id')
            ->get()
            ->filter(fn (Workday $workday): bool => $businessDays->correctionAllowed($workday->date)
                && Gate::allows('update', $workday));

        if ($workdays->isEmpty()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('ui.workdays.flash.too_soon'),
            ]);

            return back();
        }

        $type = MarkType::from($data['mark_type']);
        $reason = MarkModificationReason::from($data['reason']);

        $count = DB::transaction(fn (): int => $workdays
            ->map(fn (Workday $workday) => $manager->createModification(
                $workday,
                $type,
                $data['time'],
                $reason,
                $data['notes'] ?? null,
            ))
            ->filter()
            ->count());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.workdays.flash.bulk_modified', ['count' => $count]),
        ]);

        return back();
    }

    /**
     * Open a mark-modification request against a single workday for its entry
     * mark, exit mark, or both. The employee is notified to review each new
     * request; a mark that already has a pending request is left untouched by
     * the manager's duplicate guard.
     */
    public function modify(Request $request, Workday $workday, MarkModificationManager $manager, BusinessDayResolver $businessDays): RedirectResponse
    {
        Gate::authorize('update', $workday);

        // Art. 41 c): a correction cannot be made before the business day that
        // follows the day being corrected.
        if (! $businessDays->correctionAllowed($workday->date)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('ui.workdays.flash.too_soon'),
            ]);

            return back();
        }

        $data = $request->validate([
            'mark_in' => ['nullable', 'date_format:H:i'],
            'mark_out' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', Rule::enum(MarkModificationReason::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Only the marks whose submitted time differs from the one already on
        // the workday count as a change; a blank or unchanged picker is ignored
        // so editing one mark never opens a redundant request for the other.
        $changes = array_filter([
            'mark_in' => $this->changedMarkTime($workday->mark_in_at, $data['mark_in'] ?? null),
            'mark_out' => $this->changedMarkTime($workday->mark_out_at, $data['mark_out'] ?? null),
        ]);

        if ($changes === []) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('ui.workdays.flash.no_changes'),
            ]);

            return back();
        }

        $created = $manager->modifyFromWorkday($workday, [
            ...$changes,
            'reason' => MarkModificationReason::from($data['reason']),
            'notes' => $data['notes'] ?? null,
        ]);

        if ($created->isEmpty()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('ui.workdays.flash.modify_blocked'),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.workdays.flash.modified', ['count' => $created->count()]),
        ]);

        return back();
    }

    /**
     * The single-workday detail screen: the day's marks, its computed totals and
     * the full mark-modification history — every correction request against the
     * workday with its review state and audit trail, plus inline approve/decline
     * for the assigned reviewer.
     */
    public function show(Workday $workday, WorkdayPresenter $presenter): Response
    {
        Gate::authorize('view', $workday);

        $workday->load([
            'user:id,name,supervisor_id,overtime_rest_day_eligible',
            'shift:id,name',
            'premise:id,name',
            'leave:id,type,start_date,end_date',
            'markIn',
            'markOut',
            'markModifications' => fn ($query) => $query->latest('created_at'),
            'markModifications.mark',
            'markModifications.createdBy:id,name',
            'markModifications.reviewedBy:id,name',
            'overtimeAuthorization.user:id,supervisor_id',
            'overtimeAuthorization.reviewedBy:id,name',
            'overtimeAuthorization.revokedBy:id,name',
        ]);

        return Inertia::render('workdays/show', [
            'workday' => $presenter->workday($workday),
            'overtime' => $presenter->overtime($workday),
            'timeline' => $presenter->timeline($workday),
            'reasonOptions' => MarkModificationReason::options(),
            'compensationTypeOptions' => OvertimeCompensationType::options(),
        ]);
    }

    /**
     * Approve a pending mark-modification request from the workday detail page.
     * Only the assigned reviewer may act, and only while the request is still
     * actionable; the manager owns rewriting the mark and recalculating the day.
     */
    public function approveModification(Workday $workday, MarkModification $markModification, MarkModificationManager $manager): RedirectResponse
    {
        Gate::authorize('view', $workday);

        abort_unless($this->canReview($markModification), 403);

        $manager->approve($markModification);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.workdays.show.flash.approved'),
        ]);

        return back();
    }

    /**
     * Decline a pending mark-modification request from the workday detail page.
     * Only the assigned reviewer may act, and only while the request is still
     * actionable; the request is closed without touching the underlying mark.
     */
    public function declineModification(Workday $workday, MarkModification $markModification, MarkModificationManager $manager): RedirectResponse
    {
        Gate::authorize('view', $workday);

        abort_unless($this->canReview($markModification), 403);

        $manager->decline($markModification);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.workdays.show.flash.declined'),
        ]);

        return back();
    }

    /**
     * Approve this workday's overtime (KOL-71, moved from the old overtime
     * queue KOL-44). KOL-80: no code path opens the record ahead of this —
     * it is opened and decided right here, in the same request. The
     * permission check runs against the existing row (or an unsaved,
     * in-memory stand-in when there isn't one yet) before anything is
     * written, so a day nobody has acted on — or one a supervisor isn't
     * authorised to decide — never gets a row persisted on its behalf.
     * A flagged day is refused before it ever reaches the model, so the
     * response names the flag reason rather than a generic error (KOL-40).
     */
    public function approveOvertime(Request $request, Workday $workday): RedirectResponse
    {
        $existing = $workday->overtimeAuthorization()->first();

        Gate::authorize('approve', $existing ?? $this->provisionalAuthorization($workday));
        abort_if($existing?->isApproved() === true, 403);

        $data = $request->validate([
            'authorized_hours' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'compensation_type' => ['nullable', Rule::enum(OvertimeCompensationType::class)],
        ]);

        $authorization = OvertimeAuthorization::openFor($workday);

        if ($workday->isFlagged()) {
            throw ValidationException::withMessages([
                'reason' => __('ui.workdays.show.overtime.errors.unresolved_anomalies', [
                    'reasons' => collect($workday->anomalyFlags())
                        ->map(fn ($reason) => $reason->label())
                        ->implode(', '),
                ]),
            ]);
        }

        $compensationType = isset($data['compensation_type']) ? OvertimeCompensationType::from($data['compensation_type']) : null;

        // KOL-47: named here, not left to the model's exception, so the error
        // lands on the compensation field the approver actually chose rather
        // than reading as a missing-reason complaint.
        if ($compensationType === OvertimeCompensationType::RestDays && ! $authorization->user->overtime_rest_day_eligible) {
            throw ValidationException::withMessages([
                'compensation_type' => __('ui.workdays.show.overtime.errors.not_eligible_for_rest_days'),
            ]);
        }

        try {
            $authorization->approve(
                $request->user(),
                isset($data['authorized_hours']) ? $data['authorized_hours'].':00' : null,
                $data['reason'] ?? null,
                $compensationType,
            );
        } catch (OvertimeDecisionRefused) {
            throw ValidationException::withMessages([
                'reason' => __('ui.workdays.show.overtime.errors.reason_required'),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.workdays.show.overtime.flash.approved')]);

        return back();
    }

    /**
     * Revoke this workday's already-approved overtime (KOL-80). The record
     * is kept, not deleted — the revocation appears in the workday's
     * timeline with who, when and why. A day with no approved record has
     * nothing to revoke.
     */
    public function revokeOvertime(Request $request, Workday $workday): RedirectResponse
    {
        $authorization = $workday->overtimeAuthorization()->first();
        abort_if($authorization === null, 404);

        Gate::authorize('revoke', $authorization);
        abort_if(! $authorization->isApproved(), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $authorization->revoke($request->user(), $data['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.workdays.show.overtime.flash.revoked')]);

        return back();
    }

    /**
     * Approve a selection of workdays' overtime at once (KOL-80: bulk
     * decisions are approve-only now — objecting no longer exists, and
     * revoking is an individual-record action). Every row is authorised in
     * full (no per-row editable figure or compensation choice in bulk —
     * those stay individual-decision features); a workday with no
     * OvertimeAuthorization row yet has one opened and approved in the same
     * pass via {@see OvertimeAuthorization::openFor()}, so nothing is left
     * behind as a separately-persisted pending row. The count reported is
     * only what actually changed.
     */
    public function bulkDecideOvertime(Request $request): RedirectResponse
    {
        $organizationId = Company::currentOrganizationId();

        $data = $request->validate([
            'workdays' => ['required', 'array', 'min:1'],
            'workdays.*' => [
                'integer',
                Rule::exists('workdays', 'id')->where('organization_id', $organizationId),
            ],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $workdays = Workday::query()
            ->whereIn('id', $data['workdays'])
            ->with(['overtimeAuthorization', 'user'])
            ->get()
            ->filter(fn (Workday $workday): bool => $workday->calculated_overtime
                && $workday->calculated_overtime !== '00:00:00'
                && $workday->overtimeAuthorization?->isApproved() !== true);

        $user = $request->user();
        $decided = 0;

        DB::transaction(function () use ($workdays, $data, $user, &$decided): void {
            foreach ($workdays as $workday) {
                if (Gate::denies('approve', $workday->overtimeAuthorization ?? $this->provisionalAuthorization($workday))) {
                    continue;
                }

                $authorization = OvertimeAuthorization::openFor($workday);

                try {
                    $authorization->approve($user, reason: $data['reason'] ?? null);

                    $decided++;
                } catch (OvertimeDecisionRefused) {
                    // Left undecided: a flagged day or an unjustified cap
                    // breach in the selection is not silently approved
                    // because the rest of the batch was fine.
                }
            }
        });

        Inertia::flash('toast', [
            'type' => $decided > 0 ? 'success' : 'error',
            'message' => __('ui.workdays.show.overtime.flash.bulk_decided', [
                'decided' => $decided,
                'total' => $workdays->count(),
            ]),
        ]);

        return back();
    }

    /**
     * Whether the current user is the assigned reviewer of a still-actionable
     * request. The reviewer is the employee whose mark is being corrected, so
     * approve/decline only surface when they are the one viewing the workday.
     */
    private function canReview(MarkModification $modification): bool
    {
        return $modification->isActionable() && $modification->user_id === Auth::id();
    }

    /**
     * The submitted time for a mark when it is a real change — a different time,
     * or a time added to a currently missing mark — or null when the picker is
     * blank or matches the time already on the workday and should not open a
     * request.
     */
    private function changedMarkTime(?CarbonInterface $current, ?string $submitted): ?string
    {
        $submitted = $submitted !== '' ? $submitted : null;

        if ($submitted === null || $submitted === $current?->format('H:i')) {
            return null;
        }

        return $submitted;
    }

    /**
     * Value/label options for a tenant-owned model with a `name` column.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return array<int, array{value: string, label: string}>
     */
    private function options($query): array
    {
        return $query
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($model): array => ['value' => (string) $model->getKey(), 'label' => (string) $model->getAttribute('name')])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function employeeOptions(): array
    {
        return User::query()
            ->employees()
            ->where('organization_id', Company::currentOrganizationId())
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $employee): array => ['value' => (string) $employee->id, 'label' => $employee->name])
            ->all();
    }

    /**
     * Resolve a list of integer ids from a repeated query parameter.
     *
     * @return array<int, int>
     */
    private function idListFilter(Request $request, string $key): array
    {
        return collect((array) $request->input($key, []))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Resolve a list of backed-enum cases from a repeated query parameter,
     * discarding any value that is not a valid case.
     *
     * @template TEnum of \BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return array<int, TEnum>
     */
    private function enumListFilter(Request $request, string $key, string $enum): array
    {
        return collect((array) $request->input($key, []))
            ->map(fn ($value) => $enum::tryFrom((string) $value))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Drop the seconds from a stored HH:MM:SS time for compact display.
     */
    private function trimSeconds(?string $time): ?string
    {
        if ($time === null) {
            return null;
        }

        return Carbon::parse($time)->format('H:i');
    }
}
