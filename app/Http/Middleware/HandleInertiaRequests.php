<?php

namespace App\Http\Middleware;

use App\Enums\DocumentSignatureStatus;
use App\Enums\MarkModificationStatus;
use App\Models\DocumentSignature;
use App\Models\MarkModification;
use App\Models\OvertimeAuthorization;
use App\Models\OvertimeRequest;
use App\Services\OrganizationSettings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(private readonly OrganizationSettings $organizationSettings) {}

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'localeTag' => config('localization.supported.'.app()->getLocale(), app()->getLocale()),
            'supportedLocales' => array_keys(config('localization.supported')),
            'translations' => $this->translations(),
            'auth' => [
                'user' => $request->user(),
                'permissions' => fn () => $request->user()?->getAllPermissions()->pluck('name') ?? collect(),
                'pendingModificationsCount' => fn () => $this->pendingModificationsCount($request),
                'pendingSignaturesCount' => fn () => $this->pendingSignaturesCount($request),
                'pendingOvertimeCount' => fn () => $this->pendingOvertimeCount($request),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'dtOrganization' => fn () => $request->session()->get('dt_organization_id') === null ? null : [
                'id' => (int) $request->session()->get('dt_organization_id'),
                'name' => $request->session()->get('organization_name'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * How many mark-correction requests are awaiting the authenticated
     * employee's review, for the self-service nav badge. Zero for anyone who
     * cannot review their own corrections, so the query stays employee-only.
     */
    private function pendingModificationsCount(Request $request): int
    {
        $user = $request->user();

        if ($user === null || ! $user->getAllPermissions()->pluck('name')->contains('ReviewOwn:MarkModification')) {
            return 0;
        }

        return MarkModification::query()
            ->where('user_id', $user->id)
            ->where('status', MarkModificationStatus::Pending)
            ->count();
    }

    /**
     * How many documents are still awaiting the authenticated user's signature,
     * for the "Mis documentos" nav badge. Zero for anyone without the signing
     * permission, so the query stays signatory-only.
     */
    private function pendingSignaturesCount(Request $request): int
    {
        $user = $request->user();

        if ($user === null || ! $user->getAllPermissions()->pluck('name')->contains('SignOwn:Document')) {
            return 0;
        }

        return DocumentSignature::query()
            ->where('user_id', $user->id)
            ->where('status', DocumentSignatureStatus::Pending)
            ->count();
    }

    /**
     * How many overtime records are awaiting the authenticated user's
     * decision, for the overtime-queue nav badge — combining pending
     * OvertimeAuthorization rows (shift-excess review) and pending
     * OvertimeRequest rows (Mode-A pre-authorization), scoped exactly like
     * OvertimeQueueController::index: org-wide for Manage:OvertimeAuthorization,
     * the supervisor's own direct reports for ViewTeam/ApproveTeam, and zero
     * for anyone holding neither.
     */
    private function pendingOvertimeCount(Request $request): int
    {
        $user = $request->user();

        if ($user === null) {
            return 0;
        }

        $permissions = $user->getAllPermissions()->pluck('name');
        $isOrgWide = $permissions->contains('Manage:OvertimeAuthorization');
        $isTeamScoped = $permissions->contains('ViewTeam:OvertimeAuthorization')
            || $permissions->contains('ApproveTeam:OvertimeAuthorization');

        if (! $isOrgWide && ! $isTeamScoped) {
            return 0;
        }

        $supervisorId = $isOrgWide ? null : $user->id;

        $authorizationsCount = OvertimeAuthorization::query()
            ->pending()
            ->when($supervisorId, fn ($query) => $query->whereHas(
                'user',
                fn ($employee) => $employee->where('supervisor_id', $supervisorId),
            ))
            ->count();

        $requestsCount = $this->organizationSettings->overtimeAuthorizationMode()->allowsRequests()
            ? OvertimeRequest::query()
                ->pending()
                ->when($supervisorId, fn ($query) => $query->whereHas(
                    'user',
                    fn ($employee) => $employee->where('supervisor_id', $supervisorId),
                ))
                ->count()
            : 0;

        return $authorizationsCount + $requestsCount;
    }

    /**
     * The UI translation catalogs for the active locale, keyed by namespace.
     *
     * Laravel's lang files are the single source of truth; the frontend t()
     * helper reads this payload. Missing keys fall back to the app's
     * fallback_locale automatically via the translator.
     *
     * @return array<string, mixed>
     */
    private function translations(): array
    {
        return collect((array) config('localization.shared_namespaces'))
            ->mapWithKeys(fn (mixed $namespace) => [$namespace => trans((string) $namespace)])
            ->all();
    }
}
