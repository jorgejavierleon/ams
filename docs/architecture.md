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

The "current organization" is resolved by `BelongsToOrganization::currentOrganizationId()` (mirrored by `HolidayScope`): it prefers the DT audit session's `session('dt_organization_id')` (see *Cross-tenant reads* below), then an explicit `session('organization_id')` (set by the future tenant switcher, #48), and otherwise falls back to the authenticated user's `organization_id`. When none resolves (unauthenticated requests, console commands, seeders) the scope is a **no-op**, leaving queries unscoped — so factories/seeders must set `organization_id` explicitly.

### Public no-auth pages (mark-modification review)

The mark-modification review page (`/mark-modifications/{ulid}`, #11) is reached by employees through an emailed ULID link with **no authentication**. It deliberately relies on the scope no-op above: with no tenant resolved, the `MarkModification` lookup by ULID succeeds regardless of org. The flip side is that any org-owned model *written* from such a request (e.g. the `Mark` created when approving a missing punch) has no tenant context to stamp, so `organization_id` must be set **explicitly** from a related record (`MarkModificationManager::approve()` copies it from the workday). The review window is `ams.mark_modification_timeout_hours` (48h). **Invariant (Resolución 38 art. 40 d): silence is consent.** Once the window closes, the employee can no longer oppose, and the change *consolidates automatically* — the scheduled `mark-modifications:approve-overdue` command (`MarkModificationManager::approveOverdueModifications()`, every 10 min) approves still-pending requests. Do not "fix" the expired state into voiding the change; that inverts the law. The window is measured from `notified_at` (email send time, stamped by `StampMarkModificationNotifiedAt` on `NotificationSent`), falling back to `created_at`, so a lagging queue never shortens the worker's time to object. Separately, per **art. 41 c** a correction may only be made from the business day *after* the day being corrected (`BusinessDayResolver`, weekend- and holiday-aware), enforced in `WorkdayController::modify`/`bulkModify`.

### Cross-tenant reads (DT inspectors)

DT (Dirección del Trabajo) inspectors authenticate on the `dt` guard but carry **no** `organization_id` — they are government auditors, not tenant members. Two distinct scoping modes apply:

- **Cross-tenant tools** — the checksum-validation page (`/dt/marks/validate`, #23) deliberately runs with *no* audit organization selected: with no tenant resolved the scope is a no-op, so `Mark::where('checksum', …)` spans every employer, which is the point. It is intentionally left outside the org gate. Do not add explicit org scoping to it.
- **Audit session (org-scoped views)** — before viewing an employer's data an inspector picks one via the organization selector (`/dt/select-organization`, #26; `Dt\OrganizationController`), which stores `dt_organization_id` in the session. `currentOrganizationId()` then resolves *that* org, so every `BelongsToOrganization` model scopes to the audited employer with no per-query changes. The `dt_organization_selected` middleware (`EnsureDtOrganizationSelected`) gates these views, bouncing to the selector until a choice is made; DT logout flushes the session, clearing the selection.

The selector implements Resolución 38 **Art. 24**: an alphabetical list of employers with a **name/RUT search**, and on selection an automatic **non-nominative audit notice** (`Mail\DtAuditNotification`, fixed legal wording, castellano) to the employer's email. The **employer identity** the inspector searches and audits (`rut`, `email`, `phone`, `address`; razón social = `name`) lives on `Organization` itself — added in #26 rather than sourced from `Company`, since one organization represents one employer. Consolidating the remaining employer profile onto `Organization` / retiring `Company` is deferred to a later ticket.

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

## Documents

Employment documents (`Document`) are drafted per employee, published, and later signed. Invariants:

- **Variables resolve at publish, not at edit.** While a document is a draft, `body` holds the rich text with `{{token}}` placeholders (tokens are the `DocumentVar.key` values, seeded with braces). Publishing freezes the document: `DocumentObserver::saving` detects the `status` transition into `Published`, stamps `published_at`, and rewrites `body` through `DocumentVariableResolver`, which maps each token to the employee's real data (name, RUT, company, premise, position, organization, legal rep, dates). Do not resolve tokens on every save — only on the publish transition — or drafts lose their re-editable template. Unknown tokens are left verbatim so they surface as visible text.
- **Publishing is an action, and it derives signatories from the count.** `DocumentController::publish` delegates to `App\Actions\Documents\PublishDocument`, which flips the status (driving the observer above) and then runs `CreateDocumentSignatures`. Signatures are only spawned for **signable** types (`DocumentType::requiresSignatureConfig()` — contracts, annexes, pacts); informational types stay `Published`. The signatory set is derived, not stored per-row: the employee (always) plus the first `legal_rep_signatories` users flagged `is_legal_rep` in the org. Each gets a `DocumentSignature` (status `Pending`, numbered employee-first when `ordered_signing`) and a `DocumentSignatureRequested` mail notification, and the document moves to `PendingSignature`.
- **Signing is a firma electrónica simple authored by a one-time code.** Signatories act from the employee self-service panel (`App\Http\Controllers\My\DocumentController`, routes under `my/documents`). `SendVerificationCode` mails a 6-digit code (15-min expiry) to the signer's *personal* email; `SignDocument` validates it and records the FES evidence Ley 19.799 expects — identity, timestamp, IP, user agent, and a SHA-256 hash of the frozen body (`Document::contentHash()`). When the last signature lands, the document becomes `Signed`, a signed PDF (`SignedDocumentPdfGenerator` → `documents.signed-pdf`, stored in the `signed` media collection) is generated, and `DocumentFullySigned` is mailed. `RejectDocument` flips the document to `Rejected` and **cancels** the other pending signatures. `Document::actionableSignatureFor()` centralizes the "is it this user's turn" rule (ordered signing). Gated by `ViewOwn:Document` / `SignOwn:Document`. **The full behavioral walkthrough lives in [`document-signature-flow.md`](document-signature-flow.md).**
- **Correcting a published document is void-and-reissue, never edit-in-place.** A published body is frozen and signed against, so `DocumentController` locks edit/delete to `Draft` (403 otherwise). To fix a live document an admin **voids** it (`App\Actions\Documents\VoidDocument`, allowed only for `Published`/`PendingSignature` per `DocumentStatus::canBeVoided()`): the shared `CancelPendingSignatures` action cancels every outstanding signature and the status moves to the terminal, dedicated **`Voided`** case (chosen over reusing `Archived` so a withdrawn document reads distinctly and reports cleanly). Then **duplicate** (`DuplicateDocument`, allowed for `Voided`/`Rejected`/`Signed` per `canBeDuplicated()`) clones the document into a fresh `Draft` (title suffixed, no signatures, no dates) and redirects to its edit form. The copy is an independent document — the two are linked only through the audit trail, not a foreign key. Both actions sit in the `role:admin` route group like `publish`. `RejectDocument` reuses the same `CancelPendingSignatures`.
- **Download prefers the signed artifact.** `DocumentController::download` → `App\Actions\Documents\DownloadDocument` serves the stored signed PDF from the `signed` media collection whenever one exists (i.e. once fully signed), so the download reflects the signatures. For earlier statuses it renders on the fly via `DocumentPdfGenerator` (barryvdh/laravel-dompdf rendering `documents.pdf`), resolving variables through `DocumentVariableResolver` — a no-op for a frozen published body, a live preview for a draft. The *signed* PDF (body + `documents.signed-pdf` evidence block) is produced only at completion by `SignedDocumentPdfGenerator`; the same fallback logic backs the employee-panel download.

The editor is Tiptap (`@tiptap/react`) — see the SSR note above. The "Insert variable" picker is the shared cmdk `Command` palette listing `DocumentVar`s.

---

## Old App Reference

When implementing a feature, always check `../ams-filament` first:
- `app/Managers/` — MarkManager, LeaveManager, WorkdayCalculator (reuse, don't reimplement)
- `app/Observers/` — model observers (copy, don't rewrite)
- `app/Models/` — source of truth for model structure and relationships
- `database/seeders/` — reference for seed data and roles
