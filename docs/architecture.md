# AMS Architecture

Key decisions and conventions that aren't obvious from reading the code.

---

## Stack

Laravel 13 + Inertia v3 + React 19 + TypeScript + Tailwind v4. This is a migration of the old Filament app (`../ams-filament`). Business logic (Managers, Observers, Services) is preserved from the old app; only the UI layer is being replaced.

---

## Database

- Engine: MySQL via Laravel Sail
- Dev database: `ams` (not `laravel` — the `.env` default was wrong; corrected to `ams`)
- Test database: `testing` (clean slate per run via `RefreshDatabase`)
- The `ams` database already contains the old app's schema and data. New migrations must not conflict with existing tables.

---

## Authentication & Authorization

### Package
`spatie/laravel-permission` v8. `HasRoles` trait is on `App\Models\User`.

### Roles
Four roles, fixed (not dynamic):

| Role | Who |
|---|---|
| `admin` | Organization administrator |
| `employee` | Regular employee |
| `dt` | Dirección del Trabajo (government inspector) |
| `saas` | Platform super-admin |

Roles are seeded via `Database\Seeders\RoleSeeder` and called from `DatabaseSeeder`.

### Middleware aliases
Registered in `bootstrap/app.php`: `role`, `permission`, `role_or_permission`.

### Policy naming conventions
Most policies (Shield-generated from old app) use `TitleCase:ModelName` permission strings:
```php
$user->can('ViewAny:Company')   // CompanyPolicy::viewAny
$user->can('Create:Leave')      // LeavePolicy::create
```

`MarkPolicy` is the exception — it uses snake_case (hand-written in old app):
```php
$user->can('view_any_mark')     // MarkPolicy::viewAny
$user->can('create_mark')       // MarkPolicy::create
```

---

## Multi-tenancy

Organization-scoped via the `App\Models\Concerns\BelongsToOrganization` trait. All models belonging to an org must use this trait. It applies `App\Models\Scopes\OrganizationScope` (constrains every read to the current org) and stamps `organization_id` on creation. Never bypass this scope on org-owned models.

The "current organization" is resolved by `BelongsToOrganization::currentOrganizationId()`: it prefers an explicit `session('organization_id')` (set by the future tenant switcher, #48) and otherwise falls back to the authenticated user's `organization_id`. When neither resolves (unauthenticated requests, console commands, seeders) the scope is a **no-op**, leaving queries unscoped — so factories/seeders must set `organization_id` explicitly.

### Public no-auth pages (mark-modification review)

The mark-modification review page (`/mark-modifications/{ulid}`, #11) is reached by employees through an emailed ULID link with **no authentication**. It deliberately relies on the scope no-op above: with no tenant resolved, the `MarkModification` lookup by ULID succeeds regardless of org. The flip side is that any org-owned model *written* from such a request (e.g. the `Mark` created when approving a missing punch) has no tenant context to stamp, so `organization_id` must be set **explicitly** from a related record (`MarkModificationManager::approve()` copies it from the workday). The review window is `ams.mark_modification_timeout_hours` (48h); a still-pending request past it is treated as expired and can no longer be actioned.

### Shared/hybrid ownership (holidays)

`Holiday` is the exception to the "always org-scoped" rule. A holiday is either **official** (`organization_id = null`) — the national list synced from the Boostr API (`holidays:sync` / `App\Actions\SyncOfficialHolidays`) and managed only in the SaaS panel — or **organization-owned**. It therefore uses a dedicated `App\Models\Scopes\HolidayScope` (not `BelongsToOrganization`) that exposes *official ∪ current org* to each tenant. Tenants may CRUD their own holidays but official rows are read-only to them (enforced in `HolidayController` via `Holiday::isOfficial()`). The unique key is `(organization_id, country, date)`, so an org may add a same-date holiday alongside an official one.

---

## Artisan / Sail

The app runs inside Docker (Sail). Run Artisan commands inside the container:
```bash
docker exec ams-laravel.test-1 php artisan <command>
```

---

## Frontend Route Helpers

Use Wayfinder for all TypeScript route references. Import from `@/actions/` (controllers) or `@/routes/` (named routes). Never hardcode URL strings.

---

## Client-only libraries under SSR

Inertia SSR (`config/inertia.php` → `ssr.enabled`) evaluates page modules in Node, where `window`/`document` are absent. A library that touches the DOM at **import time** (e.g. Leaflet) will crash the server render of any page that imports it — a `mounted` state guard is not enough, because the import runs before render.

Pattern (see `MapPicker`/`MapCanvas`): put the browser-only library and its React bindings in a **separate module**, load it with `React.lazy(() => import(...))`, and gate rendering on a client check via `useSyncExternalStore(subscribe, () => true, () => false)` (returns `false` on the server, so the `import()` never runs there). Avoid `useEffect(() => setState(true))` for this — the `react-hooks/set-state-in-effect` lint rule rejects it. Wrap the map in an error boundary so its fallback (here, manual lat/lng inputs) stays usable if the library or a remote tile/geocoding service fails.

---

## Localization (i18n)

Chile ships first, so `es` (formatted as `es-CL`) is the default locale; the app is built to be translatable, with English wired end-to-end but its catalogs kept partial until an English rollout is planned. Supported locales live in `config/localization.php`.

- **Single source of truth:** Laravel lang files under `lang/{es,en}/`. There are no duplicate frontend JSON catalogs. `HandleInertiaRequests` ships the active locale's `ui` namespace to the frontend as the `translations` shared prop.
- **Invariant:** every user-visible string goes through a lang key. Add each new string to **both** `lang/es/ui.php` and `lang/en/ui.php` — never hardcode UI text in a React component.
- **Frontend usage:** `useTranslations()` returns `t('ui.nav.dashboard')` plus locale-aware `formatDate`/`formatNumber`/`formatCurrency` (driven by the `localeTag` shared prop). Server-side validation/auth messages are localized by app locale and reach the frontend already resolved via Inertia's `errors` prop — they are not shipped in `translations`.
- **Switching:** `SetLocale` middleware resolves the locale from the session (default = app locale); the `locale.update` route persists the choice. The `LanguageSwitcher` in the user menu drives it.

---

## Chilean RUT handling

RUTs are validated and formatted by the self-contained `App\Support\Rut` helper — we deliberately did **not** port the old app's `freshwork/chilean-bundle` dependency. Use it everywhere a RUT is touched:

- `App\Rules\ValidRut` — the validation rule (modulo-11 verifier check); message key `validation.rut`.
- `App\Models\Concerns\FormatedRut` — model trait that normalises the `rut` attribute to canonical `body-dv` form (e.g. `12345678-5`) on write and exposes a `formatted_rut` accessor (`12.345.678-5`) for display. Applied to `Company` and `User`.
- Normalise incoming RUTs (via `Rut::normalize`) **before** validating, so `unique` checks and stored values share the same canonical form.

Legal representatives are not a separate model: they are `User` rows with `is_legal_rep = true` and a `company_id`, exposed via `Company::representatives()`.

---

## Shifts & schedules

A `Shift` owns exactly seven `ShiftDay` rows (SQL weekday `0` = Monday … `6` = Sunday). Constraints future work must respect:

- **Lunch is stored as `lunch_start_time` + `lunch_end_time`, never as a duration.** The `WorkdayCalculator` (old app, not yet migrated) joins on those exact columns via raw SQL to compute attendance, so the schema must keep them.
- **`ShiftDay` derives its own `total_work_hours`** in a `saving` hook: `(end − start) − lunch`, or `0` when `is_free`. `Shift.total_week_hours` is rolled up from the days by `ShiftDayObserver` and is **not fillable** — set it directly, never mass-assign.
- **`ShiftObserver::created` seeds the 7 default days.** Controllers must create the shift first (letting the observer fire), then *update* those rows by weekday — do not bypass the observers or insert days manually. `ShiftController::store/update` do exactly this inside one transaction.
- **Entry/exit tolerance is a grace period, edited in minutes but stored as a `TIME`.** The UI and API use whole minutes (`30`, `120`); `ShiftController` converts them to/from `HH:MM:SS` because the `WorkdayCalculator` compares tolerance as a TIME against a mark's lateness (`ABS(TIMEDIFF(...)) BETWEEN '00:00:01' AND shifts.tolerance_in`). Keep the column a `TIME`.
- Legal ceilings live in `config/ams.php` (`max_weekly_hours`, `max_daily_hours`); the weekly cap is validated server-side on save.
- `shift_assignments` (employee → shift) has a minimal model here for the delete guard only; its full management and `ShiftAssignmentObserver` (which fires `WorkdaysRecalculationNeeded`) belong to ticket #20.

---

## Old App Reference

When implementing a feature, always check `../ams-filament` first:
- `app/Managers/` — MarkManager, LeaveManager, WorkdayCalculator (reuse, don't reimplement)
- `app/Observers/` — model observers (copy, don't rewrite)
- `app/Models/` — source of truth for model structure and relationships
- `database/seeders/` — reference for seed data and roles
