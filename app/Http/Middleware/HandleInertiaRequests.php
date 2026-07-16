<?php

namespace App\Http\Middleware;

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
        return collect(config('localization.shared_namespaces'))
            ->mapWithKeys(fn (string $namespace) => [$namespace => trans($namespace)])
            ->all();
    }
}
