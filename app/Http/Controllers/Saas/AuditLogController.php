<?php

namespace App\Http\Controllers\Saas;

use App\Concerns\ResolvesTableSort;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    use ResolvesTableSort;

    /**
     * Read-only, cross-tenant audit trail powered by Spatie Activity Log.
     *
     * Super-admins investigate activity across every organization here, so the
     * query is intentionally unscoped by tenant. The Activity model carries no
     * organization global scope, and causers (users) are not tenant-scoped
     * either, so every entry is visible.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'causer_id' => ['nullable', 'integer'],
            'organization_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $search = trim($filters['search'] ?? '') ?: null;
        $causerId = $filters['causer_id'] ?? null;
        $organizationId = $filters['organization_id'] ?? null;
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        ['sort' => $sort, 'direction' => $direction] = $this->resolveTableSort(
            $request,
            ['created_at'],
            'created_at',
            'desc',
        );

        $activities = Activity::query()
            ->with('causer:id,name,email')
            ->when($search, fn ($query) => $query->where('description', 'like', "%{$search}%"))
            ->when($causerId, fn ($query) => $query->where('causer_id', $causerId))
            ->when($organizationId, fn ($query) => $query
                ->where('causer_type', User::class)
                ->whereIn('causer_id', User::query()
                    ->where('organization_id', $organizationId)
                    ->select('id')))
            ->when($dateFrom, fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('saas/audit-log/index', [
            'activities' => $activities->through(fn (Activity $activity) => [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'event' => $activity->event,
                'description' => $activity->description,
                'subject_type' => $activity->subject_type ? class_basename($activity->subject_type) : null,
                'subject_id' => $activity->subject_id,
                'causer' => $activity->causer instanceof User
                    ? ['name' => $activity->causer->name, 'email' => $activity->causer->email]
                    : null,
                'properties' => $activity->properties?->toArray() ?: null,
                'created_at' => $activity->created_at?->format('Y-m-d H:i:s') ?? '',
            ]),
            'causers' => $this->causerOptions(),
            'organizations' => $this->organizationOptions(),
            'filters' => [
                'search' => $search,
                'causer_id' => $causerId,
                'organization_id' => $organizationId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * Users that have caused at least one logged activity, for the causer
     * filter dropdown.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function causerOptions(): array
    {
        $causerIds = Activity::query()
            ->whereNotNull('causer_id')
            ->where('causer_type', User::class)
            ->distinct()
            ->pluck('causer_id');

        return User::query()
            ->whereIn('id', $causerIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }

    /**
     * Every organization, for the organization filter dropdown. Activities are
     * attributed to an organization through their causer's `organization_id`.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function organizationOptions(): array
    {
        return Organization::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Organization $organization) => ['id' => $organization->id, 'name' => $organization->name])
            ->all();
    }
}
