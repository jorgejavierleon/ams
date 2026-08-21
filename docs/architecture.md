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

## Mobile API (`routes/api.php`)

`routes/api.php` is the employee mobile app's surface and nothing else. Every route in it lives under `/api/v1` with a `v1.` route-name prefix — no exceptions, so there is never a question of which paths are versioned.

- **Version what a client can't redeploy with you.** The app ships on its own store release cycle, so its contract must be able to outlive a backend deploy. The React frontend's own XHR endpoints (e.g. `GET /api/leaves/calendar`) deploy in the same commit as their caller, so they stay unversioned in `routes/web.php` and are *not* part of this surface despite the `/api` path.
- **Auth is a Sanctum personal access token per device.** `POST /api/v1/tokens` takes `{email, password, device_name}` and is public; tokens are keyed by `device_name`, so re-authenticating from a device replaces that device's previous token. Everything else sits behind `auth:sanctum` plus the same `permission:` gates as the web routes.
- **`DELETE /api/v1/tokens/current` is sign-out.** It deletes only `currentAccessToken()`, so an employee signing out on their phone stays signed in on other devices. Clearing client storage alone leaves the token valid on the server.
- **Dates and times in mobile payloads are naive wall-clock strings** — `Y-m-d` and `H:i:s`, no offset (`TodayResource`, `MarkResource`). The app parses them as strings and refuses anything carrying an offset, because a shift window or a punch silently re-read in the device's timezone is a different legal fact under Resolución 38 art. 8 with nothing on screen to say it moved. Never `toIso8601String()` on this surface.
- **The punch is server-authoritative.** `POST /api/v1/marks` rejects a client `datetime` outright on **both** paths (a time the device chooses is a time it can falsify, Res. 38 art. 11) and stamps the mark itself in the employee's timezone. It evaluates the geofence itself too, by haversine against the premise snapshotted onto the mark (`Geofence::verdictFor()`), and stores the verdict on `marks.geo_status`; the client's own `geo_status` travels on the request but never decides what is recorded.
- **A missing geofence never blocks a punch.** `Premise.geofence_radius_meters` is nullable and so are `lat`/`lng`; `shift.geofence` on `GET /api/v1/me/today` is null when there are no coordinates and carries a null `radius_meters` when no radius is set (`App\Support\Geofence`). All three are legitimate configurations, not errors — refusing to record a punch an employee actually made is worse than recording a suspect one. At punch time they all answer `unknown`, which is a verdict and not a failure: an out-of-range or unknown punch is recorded, flagged and still answers 201. The only refusal is the one-in-one-out-per-day guard (decision D-F1-b), which answers 409 so the app can render it as a state rather than an error.
- **A queued punch is adjudicated, not trusted — and it is the one exception to the rule above.** Res. 38 art. 10 expressly permits a device with no signal to capture a mark and transmit it later, and art. 11 hangs off that exception: the sello de tiempo is the hour the marcación *is made*. So a punch carrying the `device_datetime` + `idempotency_key` pair (both or neither, else 422) gets its `date_time` derived from the phone's reading after the server validates it, and the raw reading is kept beside the legal value permanently in `marks.device_datetime`, with `synced_at` and `captured_offline`. `captured_offline` is a stored fact, never inferred from the gap between the two datetimes — art. 10's `situaciones excepcionales` cannot be justified unless the register can say which marks were queued. The window is `ams.offline_punch_max_age_hours` (24) and `ams.offline_punch_future_tolerance_minutes`. **Past the cap the punch is neither inserted nor discarded**: art. 45.1's missed-punch alert has already gone out and art. 40 f) may already have filled the gap, so it is filed as an art. 39 b) addition (`App\Actions\FileQueuedPunchAsAddition` → `MarkModification`, employee notified, 48h to object) and answers 422. Provenance rides on the `MarkModification` so the mark it eventually consolidates into is still flagged.
- **Idempotency is a database constraint, not a lookup.** `marks` carries `unique(user_id, idempotency_key)` — per employee, never global. A replay answers **200** with the original receipt byte for byte (not 201, not an error), and is answered *before* the one-per-day guard: a punch already in the register is recorded, whatever the queue has since become old enough for.
- **Geolocation is never part of the mark's checksum; offline provenance is.** `MarkObserver` hashes SHA-256 over who punched, which way and when — coordinates, accuracy and the geofence verdict are attached afterwards, since folding them in would make a mark's integrity depend on a phone's GPS. **The formula is conditional and must stay that way** (KOL-54): a queued punch appends `|offline|<device_datetime>`, because its `date_time` was adjudicated from that reading and a `captured_offline` that could be cleared without breaking the hash would leave the register unable to say how its own timestamp was obtained (art. 8). Appending it *only* when queued is what keeps every mark recorded before offline punching existed verifiable — do not make it unconditional, which would invalidate the whole existing register. The cost is one branch in any art. 8 verification tool; this codebase's own (`/dt/marks/validate`) pays none of it, because an inspector's checksum is looked up, never recomputed.
- **A mark's folio is unique per organization, never globally.** `marks.folio` is the receipt number (`N° comprobante`, Res. 38 art. 13) in the form `YYYYMMDD-NNNN`, stamped by `MarkObserver` beside the checksum and carried identically by `MarkResource` and the emailed receipt — an employee quoting it to HR must find one number, not two. Two organizations legitimately issue `20260805-0001` on the same day, so the unique index is `(organization_id, folio)`. Numbers come from `App\Support\Folio::allocate()`, which increments a per-organization-per-day counter row in `mark_folios` under `lockForUpdate`; a read-then-increment in PHP collides at a shift change, which is precisely when punches arrive together. Never derive a folio from `mark_id`.
- **Never rate limit this surface by IP alone.** Employees at one premise punch in over the same wifi or mobile NAT, so an IP-keyed limit lets one person's bad attempts lock out everyone at exactly the moment they all need to clock in. The `api` limiter (`AppServiceProvider::configureRateLimiting()`) keys authenticated traffic per employee — it resolves `$request->user('sanctum')` itself because the group throttle runs before `auth:sanctum` — and public token issuance is keyed on email + IP by `ThrottleTokenIssuance`, which also clears the counter on a successful sign-in so a mistype never eats into the next shift's attempts.

---

## Multi-tenancy

Organization-scoped via the `App\Models\Concerns\BelongsToOrganization` trait. All models belonging to an org must use this trait. It applies `App\Models\Scopes\OrganizationScope` (constrains every read to the current org) and stamps `organization_id` on creation. Never bypass this scope on org-owned models.

The "current organization" is resolved by `BelongsToOrganization::currentOrganizationId()` (mirrored by `HolidayScope`): it prefers the DT audit session's `session('dt_organization_id')` (see *Cross-tenant reads* below), then an explicit `session('organization_id')` (set by the future tenant switcher, #48), and otherwise falls back to the authenticated user's `organization_id`. When none resolves (unauthenticated requests, console commands, seeders) the scope is a **no-op**, leaving queries unscoped — so factories/seeders must set `organization_id` explicitly.

### Public no-auth pages (mark-modification review)

The mark-modification review page (`/mark-modifications/{ulid}`, #11) is reached by employees through an emailed ULID link with **no authentication**. It deliberately relies on the scope no-op above: with no tenant resolved, the `MarkModification` lookup by ULID succeeds regardless of org. The flip side is that any org-owned model *written* from such a request (e.g. the `Mark` created when approving a missing punch) has no tenant context to stamp, so `organization_id` must be set **explicitly** from a related record (`MarkModificationManager::approve()` copies it from the workday). The review window is `ams.mark_modification_timeout_hours` (48h). **Invariant (Resolución 38 art. 40 d): silence is consent.** Once the window closes, the employee can no longer oppose, and the change *consolidates automatically* — the scheduled `mark-modifications:approve-overdue` command (`MarkModificationManager::approveOverdueModifications()`, every 10 min) approves still-pending requests. Do not "fix" the expired state into voiding the change; that inverts the law. The window is measured from `notified_at` (email send time, stamped by `StampMarkModificationNotifiedAt` on `NotificationSent`), falling back to `created_at`, so a lagging queue never shortens the worker's time to object. Separately, per **art. 41 c** a correction may only be made from the business day *after* the day being corrected (`BusinessDayResolver`, weekend- and holiday-aware), enforced in `WorkdayController::modify`/`bulkModify`.

### Cross-tenant reads (DT inspectors)

DT (Dirección del Trabajo) inspectors authenticate on the `dt` guard but carry **no** `organization_id` — they are government auditors, not tenant members. Two distinct scoping modes apply:

- **Cross-tenant tools** — the checksum-validation page (`/dt/marks/validate`, #23) deliberately runs with *no* audit organization selected: with no tenant resolved the scope is a no-op, so `Mark::where('checksum', …)` spans every employer, which is the point. It is intentionally left outside the org gate. Do not add explicit org scoping to it.
- **Audit session (org-scoped views)** — before viewing an employer's data an inspector picks one via the organization selector (`/dt/select-organization`, #26; `Dt\OrganizationController`), which stores `dt_organization_id` in the session. `currentOrganizationId()` then resolves *that* org, so every `BelongsToOrganization` model scopes to the audited employer with no per-query changes. The `dt_organization_selected` middleware (`EnsureDtOrganizationSelected`) gates these views, bouncing to the selector until a choice is made; DT logout flushes the session, clearing the selection.

The selector implements Resolución 38 **Art. 24**: an alphabetical list of employers with a **name/RUT search**, and on selection an automatic **non-nominative audit notice** (`Mail\DtAuditNotification`, fixed legal wording, castellano) to the employer's email. The **employer identity** the inspector searches and audits (`rut`, `email`, `phone`, `address`; razón social = `name`) lives on `Organization` itself — added in #26 rather than sourced from `Company`, since one organization represents one employer. KOL-32 made that a hard invariant across the schema (see *One organization, one employer* below).

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

Pattern (see `MapPicker`/`MapCanvas`, and `leaves/calendar` + `LeavesCalendarCanvas` for FullCalendar): put the browser-only library and its React bindings in a **separate module**, load it with `React.lazy(() => import(...))`, and gate rendering on a client check via `useSyncExternalStore(subscribe, () => true, () => false)` (returns `false` on the server, so the `import()` never runs there). Avoid `useEffect(() => setState(true))` for this — the `react-hooks/set-state-in-effect` lint rule rejects it. Wrap the map in an error boundary so its fallback (here, manual lat/lng inputs) stays usable if the library or a remote tile/geocoding service fails. Type-only imports from the library (`import type { EventInput } from '@fullcalendar/core'`) are erased at build and stay safe in the page module.

Tiptap (the document rich editor, `RichEditor`) is the exception that does **not** need this pattern: it is import-safe under SSR as long as `useEditor` is called with `immediatelyRender: false`. It does require the `'use no memo'` directive so the React Compiler leaves its imperative editor instance alone (same reason as `useServerTable`).

---

## Localization (i18n)

Chile ships first, so `es` (formatted as `es-CL`) is the default locale; the app is built to be translatable, with English wired end-to-end but its catalogs kept partial until an English rollout is planned. Supported locales live in `config/localization.php`.

- **Single source of truth:** Laravel lang files under `lang/{es,en}/`. There are no duplicate frontend JSON catalogs. `HandleInertiaRequests` ships the active locale's `ui` namespace to the frontend as the `translations` shared prop.
- **Invariant:** every user-visible string goes through a lang key. Add each new string to **both** `lang/es/ui.php` and `lang/en/ui.php` — never hardcode UI text in a React component.
- **Frontend usage:** `useTranslations()` returns `t('ui.nav.dashboard')` plus locale-aware `formatDate`/`formatNumber`/`formatCurrency` (driven by the `localeTag` shared prop). Server-side validation/auth messages are localized by app locale and reach the frontend already resolved via Inertia's `errors` prop — they are not shipped in `translations`.
- **Switching:** `SetLocale` middleware resolves the locale from the session (default = app locale); the `locale.update` route persists the choice. The `LanguageSwitcher` in the user menu drives it.
- **Roles & permissions are English identifiers, Spanish only in display:** Spatie role/permission names (`employee`, `ViewOwn:Mark`) are the authoritative keys that policies and `permission:` middleware gate on — never rename them for localization. `App\Support\RolePresenter` resolves the Spanish (or English) wording shown on the roles screens from `ui.roles.{names,groups,permissions}`, falling back to a title-cased identifier when a key is missing. Add a lang entry there for every new role/permission.

---

## One organization, one employer

`Company` is the **employer legal entity** — `rut`, `social_reason`, `business_line`, `company_type`, `is_est` (empresa de servicios transitorios) and its legal representatives. **An organization has exactly one** (KOL-32).

The constraint is in the schema, not just the controller: a generated column `companies.live_organization_id` mirrors `organization_id` while the row is live and is NULL once soft-deleted, and carries the unique index. That is what lets a retired employer row survive intact alongside the live one — MySQL treats NULLs in a unique index as distinct. It is `VIRTUAL` rather than `STORED` because adding a stored generated column derived from a foreign-key column fails with errno 1215.

A client that genuinely operates several RUTs (a holding) gets **one organization per RUT**, not several companies in one tenant. This is the Resolución 38-correct shape: the libro de asistencia is kept per employer RUT, and the art. 26 monthly platform upload is a client list keyed by RUT. The tenant switcher is what makes that usable day to day.

Because the employer is not a choice, `CompanyController` is a **singleton form** (`GET`/`PUT /company`) with no index, create or delete, and `EmployeeController::store` assigns `company_id` from the org's one company rather than offering a select.

Two constraints that follow from `Company` being the employer of record — the legal fields are not decoration:

- The DT attendance reports emit an `employer` column (razón social + RUT) per Resolución 38, and `MarkObserver` freezes `employer_rut` / `employer_name` onto every mark for the fiscalizador validation endpoint.
- The contract templates resolve `{{company_*}}` variables through `DocumentVariableResolver`.

## Cost centres

`CostCenter` is the accounting dimension the payroll reports call *centro de costo* — an org-scoped catalogue in the same shape as `Position`, with a name and an optional `code` (*código contable*) unique per organization. Employees reference at most one, optionally.

**A cost centre is not an employer and must never be rendered as one.** It has no RUT and no legal standing, so substituting it for the `employer` column on any art. 27 report would break the libro de asistencia. `tests/Feature/DtReportEmployerIdentityTest.php` is the guard rail against exactly that drift.

KOL-30 originally put the *código contable* on `companies` and treated the company itself as the cost-centre dimension; KOL-32 separated them, and the `convert_extra_companies_to_cost_centers` migration is what moves an existing tenant across — extra companies become cost centres, their employees keep working, and no row is destroyed.

## Contract type

`users.contract_type` is a nullable `App\Enums\ContractType`: the three contratos de trabajo the Código del Trabajo recognises for dependent workers (`indefinido`, `plazo_fijo`, `por_obra_o_faena`) plus `honorarios`.

Honorarios is deliberately a **case of the same enum, not a parallel boolean**: a separate flag could contradict the contract type, and one column that answers "what is this person's engagement" cannot. Because honorarios workers have no employment relationship, anything payroll-shaped must exclude them by asking `ContractType::isEmploymentContract()` (or `employmentContractCases()`) rather than hard-coding a case list. The RF-1 *Maestro de Trabajadores* still lists them — it is a roster, not a payroll run.

The column is nullable and was never backfilled: employees created before KOL-10 have no contract type, and reports must treat `null` as unknown rather than assume indefinido.

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
- Legal ceilings are **not** constants. They are resolved per date — see *Legal working-hour limits* below. A shift is a template with no date of its own, so `ShiftController` and `ShiftDay::exceedsLegalMaxHours` judge it against the limits in force today (today in Chile, via `TimeZoneService::today()`, not on a UTC server). The weekly cap is validated server-side on save.
- `shift_assignments` (employee → shift) has a minimal model here for the delete guard only; its full management and `ShiftAssignmentObserver` (which fires `WorkdaysRecalculationNeeded`) belong to ticket #20.

---

## Legal working-hour limits

Chile's ordinary workweek is a moving figure — Ley 21.561 takes it from 45 to 44 (26 Apr 2024), 42 (26 Apr 2026) and 40 hours (26 Apr 2028) — so it is stored as date-versioned rows in `legal_hour_limits`, never as a constant. A scalar makes a closed period change its mind: a week that read "2 hours under a 42-hour target" in 2026 would read "exactly on target" once the number became 40, which is not defensible in front of an inspector and not reconcilable against a payroll run that already happened.

Invariants future work must respect:

- **Global, not per tenant.** No `organization_id`, no per-tenant override, no `BelongsToOrganization`. These values are the law, identical for every employer in the country; a tenant able to edit them is a tenant able to raise their own overtime ceiling and have Kolvi endorse the figure in an audit. What genuinely varies per tenant is *policy* (authorisation mode, pacto requirement, thresholds), which is a separate concern. Maintenance is SaaS-panel only.
- **`App\Services\LegalHourLimits` is the only reader, and it requires a date.** There is deliberately no `current()`, no `latest()` and no argument defaulting to today. The bug class this guards against always looks the same — reaching for the newest version when you meant the applicable one — and requiring the date makes it impossible by construction rather than by everyone remembering. A date no version covers throws `MissingLegalHourLimit` instead of borrowing the nearest rule.
- **Versions are append-only.** A change in the law appends a row through `LegalHourLimitVersions::add()`. The model refuses a plain `update()`/`delete()`; the `restrict` FK from `workdays` enforces the delete half in the database. A *mistaken* version (wrong figure, wrong effective date) is fixed only through `App\Actions\CorrectLegalHourLimit`, which demands a written reason and recalculates every day the version was applied to before returning — there is no way to get the edit without the recalculation.
- **Resolve and stamp, both.** Figures come from resolving the day's own date at calculation time; `workdays.legal_hour_limit_id` records which version was applied. The stamp never drives a number — it is what makes the two disagreeing *detectable*, via `LegalHourLimitDrift`.
- **The only write surface is the SaaS panel screen** (`Saas\LegalHourLimitController`, `/saas/legal-hour-limits`, behind `role:saas,saas`), and it exposes exactly two verbs: append a version, and correct one. There is **no destroy route and no plain edit route** — the correction endpoint is the only `PUT`, and it requires a written reason, so an in-place edit is unreachable rather than merely discouraged. Both writes are logged to the SaaS audit log (`created` from `LegalHourLimitVersions::add()`, `corrected` from `CorrectLegalHourLimit`); a figure that moves payable overtime for every employer at once has to be attributable to a person and a moment. Never add a tenant-facing route that writes these rows.
- **A week is Monday–Sunday, judged against the version in force on its Monday.** Two of the three Ley 21.561 steps land mid-week (26 Apr 2024 was a Friday, 26 Apr 2028 is a Wednesday), so this is not hypothetical. The weekly cap is a budget spent across the week: applying a newly lowered ceiling from Wednesday would retroactively turn hours already lawfully worked on Monday and Tuesday into an excess against a limit that did not exist when they were worked. Taking the week's opening rule means both parties know the ceiling before the week starts and no already-worked hour changes character. Use `LegalHourLimits::forWeekOf()` for anything weekly and `on()` for anything daily — the daily caps have no straddling problem. The Monday–Sunday boundary matches `DailyReportService`, the DT-certified report.

---

## Events & listeners

**Listeners in `app/Listeners` are auto-registered — never `Event::listen()` them as well.** Laravel scans that directory and binds every `handle()`/`__invoke()` method to the event it type-hints ([events docs](https://laravel.com/docs/13.x/events#event-discovery)). A manual `Event::listen(...)` in `AppServiceProvider::boot()` for a listener that already lives there registers it a **second** time, and the handler runs twice per event — silent when the listener is idempotent, expensive when it is not (a duplicated `RecalculateWorkdays` doubled the whole test suite's runtime). `AppServiceProvider` therefore registers no listeners at all; add a manual registration only for a listener outside `app/Listeners`, and confirm the result with `sa event:list` — a class appearing both bare and as `@handle` under one event is the duplicate.

---

## Overtime calculation (OHC)

`workdays` carries **two overtime numbers that mean different things**, and conflating them is the failure this design exists to prevent:

- `extra_time` — the historical *worked span minus scheduled duration*, computed in the calculator's SQL. The Resolución 38 DT reports read it. **Do not change what it means.** Retiring it is a deliberate exercise that re-points those reports first.
- `calculated_overtime` — the OHC of PRD §7.2, built in PHP from `pre_shift_excess` (`shift start − first mark`) and `post_shift_excess` (`last mark − shift end`), both positive-only and both stored on every calculated day.

Invariants future work must respect:

- **Both excesses are stored regardless of policy; only what feeds OHC is gated.** `settings.overtime_counts_pre_shift_excess` (default **off**, per art. 32 wanting the employer's knowledge behind excess hours) decides whether the pre-shift excess is added. Because the excesses are recorded either way, enabling it later is a configuration change, never a recalculation of history — and a recurring pre-shift excess stays visible as a probable shift-definition error rather than silent overtime.
- **The policy is resolved through one seam.** `App\Services\Overtime\OvertimeExcessPolicyResolver::for($workday)` is the only thing that reads the setting; `ShiftExcess` takes the resulting `OvertimeExcessPolicy` and knows nothing about tenants. Moving the decision to the shift or to a single day (PRD §7.3's per-day override) changes the resolver and nothing in the arithmetic. The toggle is per tenant today by choice, not by structure.
- **Null means "not computed", and it is not zero.** A day with no assigned shift, or with only one mark, stores `null` in all three columns — there is no basis to claim overtime and nothing is inferred. Anomaly detection needs to see that difference.
- **Excesses are anchored to instants, not clock times, and to the second.** A shift whose `end_time` is at or before its `start_time` runs past midnight, so its end lands on the following day. Its excesses — and therefore its overtime — are attributed to the calendar day the shift **started**: a jornada is one indivisible unit, splitting it across two days would double-count the boundary and would judge each half against the wrong daily cap. No rounding happens in the engine (Resolución 38 art. 44); a report may round when it renders.
- **The engine has no word for "approved".** `workdays.overtime_state` casts to `App\Enums\OvertimeCalculationState`, whose only cases are `not_applicable` and `pending_review` (PRD §7.2: the calculation *"can reach pending review at most"*). Do not add an approved/payable case to it. The `pending | approved | revoked` state machine belongs to the authorisation record of PRD §8 — a separate row the engine never writes — and keeping the two vocabularies apart is what makes "no hour is payable without a human decision" structural rather than procedural.
- **`WorkdayCalculator::calculatedAttributes()` is the engine's entire write set.** Both write paths (the set-based upsert and `recalculateWorkday`) go through it, so anything absent from it is a column no recalculation can move — which is why `overtime_decided_at` and `overtime_decided_value` survive intact. Add a derived column there; never add a decision column there.
- **A decision that a recalculation overtook is surfaced, not resolved.** `Workday::overtimeNeedsReReview()` / `->needsOvertimeReReview()` derive staleness by comparing the decided figure to the current one (NULL-safe, via `<=>`: a day that had a figure at decision time and has none now is exactly the case a reviewer must see). Nothing auto-approves and nothing is silently kept.
- **Every pass is idempotent, and re-running is the normal case.** Dates are upserted on the `(user_id, date)` unique index, so a backfill overlapping processed days corrects them in place and leaves identical figures where no input moved. `App\Jobs\CalculateOvertime` is deliberately **not** `ShouldBeUnique` — suppressing an overlapping dispatch would drop a correction nobody ever processes; idempotency is the upsert's job.
- **Two entry points, two intents.** `overtime:calculate` (scheduled daily, `--organization/--from/--to` for backfill) *creates* missing days. The `WorkdaysRecalculationNeeded` event — raised by `LeaveObserver` and `ShiftAssignmentObserver`, consumed by `App\Listeners\RecalculateWorkdays` — only *recomputes days already rolled up* (`recalculateComputedDate`). An assignment backdated a month is not an instruction to manufacture a month of absences; backfilling is a deliberate act.
- **The tenant boundary is `users.organization_id` in the calculator query.** The job takes one organization id and the whole query hangs off the employee, so marks, assignments and leaves can only reach rows inside it. A recalculation spanning employees of two tenants is dispatched as one job each.
- **Known gap:** the calculator joins marks by calendar date, so an overnight shift's exit mark, landing on the next day, is not yet paired to the workday that owns it — such a day reads as one mark and computes no OHC. Fixing the join moves `extra_time`, `worked_time` and `status` for those days, so it is its own task, not a side effect of another.

---

## Overtime authorisation (OHA)

`overtime_authorizations` (`App\Models\OvertimeAuthorization`, PRD §8) is **the only row from which a payable overtime hour can be born**. `workdays` says what an employee's marks imply; this table says what the employer owes. The engine writes the first and never the second; payroll reads the second and never the first.

- **Never derive payable hours from attendance at read time.** A report, an export or a summary that recomputes overtime from marks has just paid hours nobody approved. Ask `Workday::authorizedOvertime()` / `unauthorizedOvertime()`, or read the record directly. New exports read `OvertimeAuthorization::approved()`; they do not join their way to `calculated_overtime`.
- **The three figures stay apart.** `calculated_hours` (OHC, snapshotted when the record opens), `requested_hours` (OHR, Mode A only), `authorized_hours` (OHA) and the derived `final_hours` are four columns, not one. Collapsing them makes the payable figure unexplainable — "why two hours and not three" has to be answerable off a single row. All are nullable, and **null is "this tenant has no such figure", not zero**: `Duration::min()` skips the absent ones rather than flooring the result to nothing.
- **There is no lapsed status, and there must never be one.** `App\Enums\OvertimeAuthorizationStatus` has exactly `pending | approved | revoked` (PRD §7.5: overtime is *never* auto-approved by timeout). This is the deliberate opposite of `MarkModificationStatus`, where silence *does* consolidate after the art. 40 d window: there, silence confirms a correction the employer already made; here it would create a payment obligation nobody agreed to.
- **KOL-80: nothing eagerly opens a record any more, and `pending` is never a state anyone lists or waits in.** A day with `calculated_overtime > 0` and no `OvertimeAuthorization` row is simply "not opened", computed on read (`WorkdayController::overtimeRowData()`, `WorkdayPresenter::overtime()`) — never stored. The only callers of `OvertimeAuthorization::openFor()` left in `app/` are `WorkdayController::approveOvertime()`/`bulkDecideOvertime()`, and both call it immediately followed by `approve()` in the same request: the row is born already-decided. Do not add a call to `openFor()` from a read path (index, presenter, report) — that recreates the eager-queue premise KOL-70 was rejected for and KOL-80 undid.
- **There is no "objected" status.** Silence — never approving — is sufficient refusal; KOL-80 removed `OvertimeAuthorizationStatus::Objected`, `OvertimeAuthorization::object()`, and every "objetar" affordance in the UI. Do not re-add an objection path; if a day should not be paid, nobody approves it.
- **The guarantee is on the write path, not at the call sites.** `OvertimeAuthorization::booted()` refuses to save any row in a status that `requiresReviewer()` without both `reviewed_by` and `reviewed_at` (`OvertimeDecisionRefused::withoutAReviewer()`). A cron, a backfill or a future queue job hits it exactly as a controller does, so no code path can age a record into being payable. Do not add a write path that sets `status` without a reviewer.
- **`approve()` is the only way out of pending, and `revoke()` is the only way out of approved.** Both take a `User` as their first argument — there is no signature that omits the human — and both refuse a reviewer from another tenant (`OvertimeDecisionRefused::byAnotherTenant()`), covering the write the way `OrganizationScope` covers the read. `revoke()` additionally refuses a record that is not currently approved (`OvertimeDecisionRefused::withoutApproval()`). Revoking keeps the row — `revoked_by`/`revoked_at`/`revoked_reason` are separate columns from the original approval's `reviewed_by`/`reviewed_at`/`reason`, so a revoked record still answers who approved it and who later withdrew that approval as two distinct facts. There is no un-revoke; approving again after a revocation is a fresh decision on the same row.
- **Unauthorised hours are kept, not dropped.** `unauthorizedOvertime()` is the calculated figure less the payable one, so a pending, revoked or partially authorised day all stay reportable (KOL-13, KOL-24) without ever merging into a payable total.
- **`final_hours` is `MIN(OHA, OHC)` — never a third term.** The PRD text still reads `MIN(OHR, OHA, OHC)`; that has been superseded (`backlog/decisions/decision-1`) because the three-term form pays only the requested amount when a supervisor authorises *more* than was asked for and the worker works it — underpaying employer-known, worked hours. The requested figure stays on the record as evidence and as the Mode A gate, never in the arithmetic. A missing calculated figure is excluded from the comparison, not treated as zero, via `Duration::min()`.
- **`approve()` stamps `Workday::overtime_decided_at`/`overtime_decided_value`** with the reviewer's timestamp and the frozen `calculated_hours` the decision was made against — the one write path allowed to touch those columns (KOL-39 built them and left them unwritten on purpose; `revoke()` does not touch them either). This is what makes `Workday::overtimeNeedsReReview()` real: `OvertimeAuthorization` never re-syncs its own `calculated_hours` snapshot after `openFor()`, so an approved figure cannot silently rise when the engine later recomputes a bigger one — the day surfaces as needing re-review instead.
- **`overtime_pact_id` is intentionally unconstrained** until KOL-42 creates the pactos table, which adds the foreign key. It stays nullable afterwards: a missing pacto demands a written justification, never a bar to payment (`backlog/decisions/decision-1`).
- **Hour arithmetic goes through `App\Support\Duration`**, not through the `HH:MM:SS` strings. Comparing those lexically gets `'10:00:00'` vs `'9:00:00'` wrong, and Resolución 38 art. 44 leaves no room for intermediate rounding. `Duration` holds whole seconds, clamps at zero, and preserves null through `tryFrom()`.
- **A permission check on a day with no row yet cannot load one to check it.** `WorkdayController`/`WorkdayPresenter` both carry a private `provisionalAuthorization(Workday)` helper — an unsaved `OvertimeAuthorization` with only the `user` relation set, built purely so `Gate::allows('approve', …)` can evaluate `OvertimeAuthorizationPolicy` without a write or an extra query. Never call `openFor()` just to answer "can this user decide this day" on a read path; use the provisional instance instead.

### Rest-day compensation (KOL-47)

`App\Enums\OvertimeCompensationType` (`payment | rest_days`) is **not** stored on any pacto or tenant setting — KOL-56 removed the org-level version of this vocabulary, and an earlier pass of this task incorrectly put it back on `OvertimePact` before being corrected. The real design: `User.overtime_rest_day_eligible` is a standing, admin-managed eligibility flag on the employee's profile, independent of whether they hold any pacto at all. The compensation type itself is the **approver's choice, per record**, passed as `OvertimeAuthorization::approve()`'s `$compensationType` argument — honoured only when the employee is eligible (`OvertimeDecisionRefused::notEligibleForRestDayCompensation()` otherwise) and defaulting to `Payment` whenever omitted. Nothing derives it automatically from a pacto, a tenant setting, or anything else — the choice is made live, at the moment of decision, by whoever is deciding.

- **The exchange rate and expiry window are statutory, not chosen.** Art. 32 §4 fixes 1 overtime hour at 1.5 rest-hours, and the window to use accrued rest at six months from the day the overtime was worked (`accrual_date`, the worked date — not the approval date). Do not "round" the ratio away or invent a different window; both are cited in KOL-47's task notes with the primary source.
- **`rest_hours`, not `accrued_hours`, is the spendable balance.** `OvertimeRestDayBalance.accrued_hours` is the approved OT figure kept only for the audit trail; `rest_hours = accrued_hours × 1.5` is what `consumed_hours` is drawn against. One balance row per rest-day-compensated `OvertimeAuthorization` (unique FK), created by `RestDayBalanceService::accrueFor()` inside `approve()`.
- **Expired-unconsumed balance is payable, never forfeited.** Art. 32 §4: unused accrued rest must be paid in that period's payroll if not taken in time — it does not lapse into nothing. `RestDayBalanceService::sweepExpired()` (daily schedule) stamps unswept, past-`expiry_date` lines with `expired_at`; `OvertimeRestDayBalance::payableFromExpiry()` converts the unconsumed remainder back out of the 1.5x ratio into payable OT hours.
- **That conversion never touches the original `OvertimeAuthorization` row.** Consumption can be partial, and an authorization is one row per workday — there is no way to "flip" just the unconsumed slice of a day's compensation type back to payment. `OvertimeAuthorization::scopeExportable()` (`approved` + `compensation_type = payment`) is therefore an absolute, permanent exclusion for rest-day-compensated records: it is true today and stays true after expiry. The expired-unpaid remainder is a **second, distinct payable source** living only on `OvertimeRestDayBalance`. **KOL-49's export must union both sources** or it will silently under-pay anyone whose rest-day balance lapsed unused — this is flagged on KOL-49's task notes, not yet built.
- **Consumption is a ledger, not a running total.** Every decrement is its own `OvertimeRestDayConsumption` row pointing at the balance line it drew from (`RestDayBalanceService::consume()`, FIFO by soonest `expiry_date` across an employee's unexpired lines), so "why is this balance lower than it was accrued" is always answerable from the data.
- **Not implemented, flagged rather than guessed at:** the art. 32 §4 cap of 5 business days/year a pacto may agree to compensate in rest (a constraint on the pacto's terms), the 48-hour advance-notice requirement for an employee to request use of accrued rest (no self-service consumption flow exists yet — HR registers consumption today), and art. 73 termination settlement. See KOL-47's task notes for the full citation and reasoning.

---

## Documents

Employment documents (`Document`) are drafted per employee, published, and later signed. Invariants:

- **Variables resolve at publish, not at edit.** While a document is a draft, `body` holds the rich text with `{{token}}` placeholders (tokens are the `DocumentVar.key` values, seeded with braces). Publishing freezes the document: `DocumentObserver::saving` detects the `status` transition into `Published`, stamps `published_at`, and rewrites `body` through `DocumentVariableResolver`, which maps each token to the employee's real data (name, RUT, company, premise, position, organization, legal rep, dates). Do not resolve tokens on every save — only on the publish transition — or drafts lose their re-editable template. Unknown tokens are left verbatim so they surface as visible text.
- **Publishing is an action, and it derives signatories from the count.** `DocumentController::publish` delegates to `App\Actions\Documents\PublishDocument`, which flips the status (driving the observer above) and then runs `CreateDocumentSignatures`. Signatures are only spawned for **signable** types (`DocumentType::requiresSignatureConfig()` — contracts, annexes, pacts); informational types stay `Published`. The signatory set is derived, not stored per-row: the employee (always) plus the first `legal_rep_signatories` users flagged `is_legal_rep` in the org. Each gets a `DocumentSignature` (status `Pending`, numbered employee-first when `ordered_signing`) and a `DocumentSignatureRequested` mail notification, and the document moves to `PendingSignature`.
- **Signing is a firma electrónica simple authored by a one-time code.** Signatories act from the employee self-service panel (`App\Http\Controllers\My\DocumentController`, routes under `my/documents`). `SendVerificationCode` mails a 6-digit code (15-min expiry) to the signer's *personal* email; `SignDocument` validates it and records the FES evidence Ley 19.799 expects — identity, timestamp, IP, user agent, and a SHA-256 hash of the frozen body (`Document::contentHash()`). When the last signature lands, the document becomes `Signed`, a signed PDF (`SignedDocumentPdfGenerator` → `documents.signed-pdf`, stored in the `signed` media collection) is generated, and `DocumentFullySigned` is mailed. `RejectDocument` flips the document to `Rejected` and **cancels** the other pending signatures. `Document::actionableSignatureFor()` centralizes the "is it this user's turn" rule (ordered signing). Gated by `ViewOwn:Document` / `SignOwn:Document`. **The full behavioral walkthrough lives in [`document-signature-flow.md`](document-signature-flow.md).**
- **Correcting a published document is void-and-reissue, never edit-in-place.** A published body is frozen and signed against, so `DocumentController` locks edit/delete to `Draft` (403 otherwise). To fix a live document an admin **voids** it (`App\Actions\Documents\VoidDocument`, allowed only for `Published`/`PendingSignature` per `DocumentStatus::canBeVoided()`): the shared `CancelPendingSignatures` action cancels every outstanding signature and the status moves to the terminal, dedicated **`Voided`** case (chosen over reusing `Archived` so a withdrawn document reads distinctly and reports cleanly). Then **duplicate** (`DuplicateDocument`, allowed for `Voided`/`Rejected`/`Signed` per `canBeDuplicated()`) clones the document into a fresh `Draft` (title suffixed, no signatures, no dates) and redirects to its edit form. The copy is an independent document — the two are linked only through the audit trail, not a foreign key. Both actions sit in the `role:admin` route group like `publish`. `RejectDocument` reuses the same `CancelPendingSignatures`.
- **Download prefers the signed artifact.** `DocumentController::download` → `App\Actions\Documents\DownloadDocument` serves the stored signed PDF from the `signed` media collection whenever one exists (i.e. once fully signed), so the download reflects the signatures. For earlier statuses it renders on the fly via `DocumentPdfGenerator` (barryvdh/laravel-dompdf rendering `documents.pdf`), resolving variables through `DocumentVariableResolver` — a no-op for a frozen published body, a live preview for a draft. The *signed* PDF (body + `documents.signed-pdf` evidence block) is produced only at completion by `SignedDocumentPdfGenerator`; the same fallback logic backs the employee-panel download.

The editor is Tiptap (`@tiptap/react`) — see the SSR note above. The "Insert variable" picker is the shared cmdk `Command` palette listing `DocumentVar`s.

---

## Organization settings

Per-organization configuration (notification toggles, document defaults) lives in a single `Setting` row per organization (`BelongsToOrganization`, one-per-org enforced by a unique `organization_id`). Invariants:

- **Read through `App\Services\OrganizationSettings`, never query `Setting` directly.** `->current()` returns the org's row as a live model (creating it with the model's `$attributes` defaults on first access) for read+write; `->get($key, $default)` is the cached hot path for scalar reads. `organization_id` is **not fillable** — a settings row must never change tenant through mass assignment — so `current()` stamps it by hand on creation; the `BelongsToOrganization` creating hook only covers a request with an active tenant, and the calculation engine reads settings from the console where there is none. With no organization at all it returns an unsaved model rather than writing an orphan row. The cache stores a **plain attributes array, never the Eloquent model** — a serialized model round-trips to `__PHP_Incomplete_Class` in a real cache store, so caching the model directly 500s on the next request.
- **Writes must go through Eloquent so the cache stays fresh.** `SettingObserver::saved` invalidates the cached array on every create/update. A raw `DB::table('settings')->update(...)` bypasses the observer and leaves stale reads — don't do it. The `SettingController@update` saves via `$setting->update()` for this reason.
- **Route naming: `/organization-settings` (admin-only) is distinct from the starter-kit `/settings/*`**, which are the *personal* user pages (profile, security, appearance). Org settings sit in the `role:admin` group and appear under the sidebar "Settings" group; they deliberately do not reuse `/settings` to avoid clobbering the personal-settings redirect.
- **Some toggles are stored but not yet consumed.** `leave_approval_notification` and `documents_require_ordered_signing` persist a preference that their respective features do not read yet; wiring them into the leave-approval mail and the document create-form default is a follow-up.

---

## Old App Reference

When implementing a feature, always check `../ams-filament` first:
- `app/Managers/` — MarkManager, LeaveManager, WorkdayCalculator (reuse, don't reimplement)
- `app/Observers/` — model observers (copy, don't rewrite)
- `app/Models/` — source of truth for model structure and relationships
- `database/seeders/` — reference for seed data and roles
