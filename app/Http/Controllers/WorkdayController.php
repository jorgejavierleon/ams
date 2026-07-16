<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesTableSort;
use App\Enums\MarkModificationReason;
use App\Enums\MarkModificationStatus;
use App\Enums\MarkType;
use App\Enums\WorkdayStatus;
use App\Managers\MarkModificationManager;
use App\Models\Company;
use App\Models\MarkModification;
use App\Models\Position;
use App\Models\Premise;
use App\Models\User;
use App\Models\Workday;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
        Gate::authorize('viewAny', Workday::class);

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

        $workdays = Workday::query()
            ->with([
                'user:id,name,position_id',
                'shift:id,name',
                'leave:id,type',
            ])
            ->withCount('pendingMarkModifications')
            ->betweenDates($from, $to)
            ->when($statuses, fn ($query) => $query->whereIn('status', $statuses))
            ->when($employeeIds, fn ($query) => $query->whereIn('user_id', $employeeIds))
            ->when($premiseIds, fn ($query) => $query->whereIn('premise_id', $premiseIds))
            ->when($positionIds, fn ($query) => $query->whereHas(
                'user',
                fn ($user) => $user->whereIn('position_id', $positionIds),
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
            ]),
            'filters' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'statuses' => array_map(fn (WorkdayStatus $status) => $status->value, $statuses),
                'employees' => array_map('strval', $employeeIds),
                'positions' => array_map('strval', $positionIds),
                'premises' => array_map('strval', $premiseIds),
                'sort' => $sort,
                'direction' => $direction,
            ],
            'statusOptions' => WorkdayStatus::options(),
            'employeeOptions' => $this->employeeOptions(),
            'positionOptions' => $this->options(Position::query()),
            'premiseOptions' => $this->options(Premise::query()),
            'reasonOptions' => MarkModificationReason::options(),
            'markTypeOptions' => MarkType::options(),
        ]);
    }

    /**
     * Open a pending mark-modification request against each selected workday.
     * The requests surface as pending indicators for HR to review and approve.
     */
    public function bulkModify(Request $request): RedirectResponse
    {
        Gate::authorize('update', Workday::class);

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

        $workdays = Workday::query()->whereIn('id', $data['workdays'])->get();

        DB::transaction(function () use ($workdays, $data, $request): void {
            foreach ($workdays as $workday) {
                MarkModification::create([
                    'workday_id' => $workday->id,
                    'mark_id' => $data['mark_type'] === MarkType::In->value
                        ? $workday->mark_in_id
                        : $workday->mark_out_id,
                    'user_id' => $workday->user_id,
                    'created_by' => $request->user()->id,
                    'reason' => $data['reason'],
                    'status' => MarkModificationStatus::Pending,
                    'date_time' => $workday->date->copy()->setTimeFromTimeString($data['time']),
                    'mark_type' => $data['mark_type'],
                    'notes' => $data['notes'] ?? null,
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.workdays.flash.bulk_modified', ['count' => $workdays->count()]),
        ]);

        return back();
    }

    /**
     * Open a mark-modification request against a single workday for its entry
     * mark, exit mark, or both. The employee is notified to review each new
     * request; a mark that already has a pending request is left untouched by
     * the manager's duplicate guard.
     */
    public function modify(Request $request, Workday $workday, MarkModificationManager $manager): RedirectResponse
    {
        Gate::authorize('update', $workday);

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
    public function show(Workday $workday): Response
    {
        Gate::authorize('view', $workday);

        $workday->load([
            'user:id,name',
            'shift:id,name',
            'premise:id,name',
            'leave:id,type,start_date,end_date',
            'markIn',
            'markOut',
            'markModifications' => fn ($query) => $query->latest('created_at'),
            'markModifications.mark',
            'markModifications.createdBy:id,name',
            'markModifications.reviewedBy:id,name',
        ]);

        return Inertia::render('workdays/show', [
            'workday' => $this->presentWorkday($workday),
            'modifications' => $workday->markModifications
                ->map(fn (MarkModification $modification) => $this->presentModification($modification))
                ->all(),
            'reasonOptions' => MarkModificationReason::options(),
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
     * Whether the current user is the assigned reviewer of a still-actionable
     * request. The reviewer is the employee whose mark is being corrected, so
     * approve/decline only surface when they are the one viewing the workday.
     */
    private function canReview(MarkModification $modification): bool
    {
        return $modification->isActionable() && $modification->user_id === Auth::id();
    }

    /**
     * Shape a workday for the detail page: identity, computed totals and each
     * mark card with its current modification state.
     *
     * @return array<string, mixed>
     */
    private function presentWorkday(Workday $workday): array
    {
        return [
            'id' => $workday->id,
            'date' => $workday->date->format('Y-m-d'),
            'date_label' => $workday->date->locale(app()->getLocale())->isoFormat('dddd D [de] MMMM [de] YYYY'),
            'employee' => [
                'id' => $workday->user_id,
                'name' => $workday->user?->name,
            ],
            'status' => $workday->status?->value,
            'status_label' => $workday->status?->label(),
            'status_badge' => $workday->status?->badge(),
            'shift' => $workday->shift?->name,
            'shift_timeframe' => $this->shiftTimeframe($workday),
            'shift_start' => $this->timeOfDay($workday->shift_start_time),
            'shift_end' => $this->timeOfDay($workday->shift_end_time),
            'premise' => $workday->premise?->name,
            'leave' => $workday->leave === null ? null : [
                'type' => $workday->leave->type->label(),
                'start_date' => $workday->leave->start_date->format('d/m/Y'),
                'end_date' => $workday->leave->end_date->format('d/m/Y'),
            ],
            'mark_in' => $this->presentMark($workday, MarkType::In),
            'mark_out' => $this->presentMark($workday, MarkType::Out),
            'worked_time' => $this->trimSeconds($workday->worked_time),
            'extra_time' => $this->trimSeconds($workday->extra_time),
            'missing_time' => $this->trimSeconds($workday->missing_time),
        ];
    }

    /**
     * Shape one mark card: its registered time, whether it currently carries a
     * pending or already-applied correction, and the full legal snapshot shown
     * in the mark-detail dialog.
     *
     * @return array<string, mixed>
     */
    private function presentMark(Workday $workday, MarkType $type): array
    {
        $mark = $type === MarkType::In ? $workday->markIn : $workday->markOut;
        $typeModifications = $workday->markModifications->where('mark_type', $type);

        return [
            'type' => $type->value,
            'time' => $mark?->date_time?->format('H:i:s'),
            'scheduled' => $this->timeOfDay(
                $type === MarkType::In ? $workday->shift_start_time : $workday->shift_end_time,
            ),
            'has_pending' => $typeModifications->contains(fn (MarkModification $modification) => $modification->isPending()),
            'is_modified' => $typeModifications->contains(
                fn (MarkModification $modification) => $modification->status === MarkModificationStatus::Approved,
            ),
            'details' => $mark === null ? null : [
                'date' => $mark->date_time->format('d/m/Y'),
                'time' => $mark->date_time->format('H:i:s'),
                'type' => $mark->type->label(),
                'shift' => $mark->shift_start_time && $mark->shift_end_time
                    ? $mark->shift_start_time->format('H:i').' - '.$mark->shift_end_time->format('H:i')
                    : null,
                'employee_name' => $mark->employee_name,
                'employee_rut' => $mark->employee_rut,
                'employer_name' => $mark->employer_name,
                'employer_rut' => $mark->employer_rut,
                'premise_name' => $mark->premise_name,
                'premise_address' => $mark->premise_address,
                'coordinates' => $mark->lat && $mark->lng ? $mark->lat.', '.$mark->lng : null,
            ],
        ];
    }

    /**
     * Shape one mark-modification history row: its proposed change, review state
     * and the full audit trail (who requested and reviewed it, and when).
     *
     * @return array<string, mixed>
     */
    private function presentModification(MarkModification $modification): array
    {
        return [
            'id' => $modification->id,
            'mark_type' => $modification->mark_type?->value,
            'mark_type_label' => $modification->mark_type?->label(),
            'status' => $modification->status?->value,
            'status_label' => $modification->status?->label(),
            'status_badge' => $modification->status?->badge(),
            'original_time' => $modification->mark?->date_time?->format('H:i:s'),
            'modified_time' => $modification->date_time->format('H:i:s'),
            'reason' => $modification->reason?->label(),
            'notes' => $modification->notes,
            'created_by' => $modification->createdBy?->name,
            'created_at' => $modification->created_at?->format('d/m/Y H:i'),
            'created_ago' => $modification->created_at?->diffForHumans(),
            'reviewed_by' => $modification->reviewedBy?->name,
            'reviewed_at' => $modification->reviewed_at?->format('d/m/Y H:i'),
            'reviewed_ago' => $modification->reviewed_at?->diffForHumans(),
            'can_review' => $this->canReview($modification),
        ];
    }

    /**
     * The workday's scheduled shift window as `HH:MM - HH:MM`, or null when no
     * shift is assigned.
     */
    private function shiftTimeframe(Workday $workday): ?string
    {
        if ($workday->shift_start_time === null || $workday->shift_end_time === null) {
            return null;
        }

        return Carbon::parse($workday->shift_start_time)->format('H:i').' - '.
            Carbon::parse($workday->shift_end_time)->format('H:i');
    }

    /**
     * Normalise a stored `TIME` value to `HH:MM`, or null when unset.
     */
    private function timeOfDay(?string $time): ?string
    {
        if ($time === null) {
            return null;
        }

        return Carbon::parse($time)->format('H:i');
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
            ->map(fn ($model): array => ['value' => (string) $model->id, 'label' => $model->name])
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
