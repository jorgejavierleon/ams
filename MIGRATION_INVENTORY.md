# AMS Migration Inventory
## Filament → Laravel React Starter Kit (React 19 + TypeScript + Inertia.js v3 + shadcn/ui)

> Generated: 2026-06-22  
> Source: Laravel 12 / Filament v5 / PHP 8.4  
> Target: React 19, TypeScript, Inertia.js v3, shadcn/ui

---

## 1. Module Map

The application has no feature-module folder structure — all code lives flat under `app/`. The logical modules below are inferred from model groupings, navigation groups, and panel separation.

| Module | Path(s) | Responsibility | Filament Resources | Jobs | Events | Observers | Listeners |
|---|---|---|---|---|---|---|---|
| **Employees** | `app/Models/User.php`, `app/Filament/Resources/Employees/` | Employee CRUD, profile, shift assignments, document management | Yes (1) | No | No | Yes (`UserObserver`) | No |
| **Companies & Premises** | `app/Models/Company.php`, `app/Models/Premise.php`, `app/Filament/Resources/Companies/`, `app/Filament/Resources/Premises/` | Company hierarchy, physical locations (with Google Maps) | Yes (2) | No | No | No | No |
| **Positions** | `app/Models/Position.php`, `app/Filament/Resources/Positions/` | Job positions, employee grouping | Yes (1) | No | No | No | No |
| **Shifts** | `app/Models/Shift.php`, `app/Models/ShiftDay.php`, `app/Models/ShiftAssignment.php`, `app/Filament/Resources/Shifts/` | Shift scheduling, per-day configuration, employee assignments | Yes (1) | No | No | Yes (`ShiftObserver`, `ShiftDayObserver`, `ShiftAssignmentObserver`) | No |
| **Marks** | `app/Models/Mark.php`, `app/Managers/MarkManager.php`, `app/Observers/MarkObserver.php` | Clock-in/clock-out recording, checksum integrity | No (Admin) | No | No | Yes (`MarkObserver`) | No |
| **Workdays** | `app/Models/Workday.php`, `app/Filament/Resources/Workdays/`, `app/Services/WorkdayCalculator.php` | Daily attendance aggregation, status calculation, mark corrections | Yes (1) | No | No | No | No |
| **Mark Modifications** | `app/Models/MarkModification.php`, `app/Managers/MarkModificationManager.php`, `app/Filament/Pages/MarkModification/` | Correction workflow for wrong/missing marks, approval/rejection | No (page only) | No | No | No | No |
| **Leaves** | `app/Models/Leave.php`, `app/Managers/LeaveManager.php`, `app/Filament/Resources/Leaves/` | Vacation/medical/personal leave, approval workflow, balance tracking | Yes (1) | No | Yes (`WorkdaysRecalculationNeeded`) | Yes (`LeaveObserver`) | Yes (`RecalculateWorkdaysListener`) |
| **Documents** | `app/Models/Document.php`, `app/Models/DocumentSignature.php`, `app/Models/DocumentTemplate.php`, `app/Filament/Resources/Documents/` | Contracting documents, digital signature workflow | Yes (2) | No | No | Yes (`DocumentObserver`) | No |
| **Document Variables** | `app/Models/DocumentVar.php`, `app/Filament/Saas/Resources/DocumentVars/` | Global template variable definitions (SaaS panel) | Yes (1 — SaaS) | No | No | No | No |
| **Holidays** | `app/Models/Holiday.php`, `app/Filament/Resources/Holidays/` | Chilean public holidays, mandatory/optional flags | Yes (1) | No | No | No | No |
| **DT Reports** | `app/Filament/Dt/Pages/Reports/`, `app/Services/Reports/`, `app/Livewire/Reports/` | Government labor-inspection reports (attendance, daily, shift changes, incidents, Sundays) | No (pages only) | No | No | No | No |
| **Settings** | `app/Models/Setting.php`, `app/Filament/Pages/Settings/`, `app/Livewire/Settings/` | Per-organization notification preferences, document settings | No (page only) | No | No | Yes (`SettingObserver`) | No |
| **Organizations** | `app/Models/Organization.php`, `app/Filament/Saas/Resources/Organizations/` | Multi-tenant organization management (SaaS panel) | Yes (1 — SaaS) | No | No | Yes (`OrganizationObserver`) | No |
| **Auth** | `app/Filament/Pages/Auth/`, `app/Filament/Dt/Pages/Auth/` | Login, register, password reset, profile editing per panel | No | No | No | No | Yes (login/logout session listeners) |

**Total Filament Resources: 16** (10 Admin, 3 DT, 3 SaaS)

---

## 2. Filament Resources Inventory

### Admin Panel (`app/Filament/Resources/`)

---

#### EmployeeResource
- **File:** `app/Filament/Resources/Employees/EmployeeResource.php`
- **Model:** `App\Models\User`
- **Navigation Group:** `organization`

**Table Columns:**
| Name | Type | Sortable | Searchable |
|---|---|---|---|
| full_name | Custom (`AvatarUserName` column) | No | No |
| email | TextColumn | Yes | Yes |
| rut | TextColumn | No | Yes |
| position.name | TextColumn | No | No |
| premise.name | TextColumn | No | No |
| phone | TextColumn | No | No |
| is_active | ToggleColumn | No | No |
| is_admin | IconColumn | No | No |
| nationality | TextColumn | No | No |
| gender | TextColumn | No | No |
| created_at | TextColumn | Yes | No |

**Form Fields (tabbed, 5 tabs):**
| Name | Field Type | Required | Relationship |
|---|---|---|---|
| is_active | Toggle | No | — |
| avatar | SpatieMediaLibraryFileUpload | No | Media (`avatar`) |
| first_name | TextInput | Yes | — |
| last_name | TextInput | Yes | — |
| second_last_name | TextInput | No | — |
| email | TextInput | Yes | — |
| password | TextInput | No | — |
| rut | TextInput | Yes | — |
| nationality | TextInput | No | — |
| gender | Select | No | — |
| company_id | Select | No | `belongsTo(Company)` |
| premise_id | Select | No | `belongsTo(Premise)` |
| position_id | Select | No | `belongsTo(Position)` |
| supervisor_id | Select | No | self-ref `belongsTo(User)` |
| contract_start_date | DatePicker | No | — |
| contract_end_date | DatePicker | No | — |
| is_admin | Toggle | No | — |
| vacation_days | TextInput (numeric) | No | — |
| additional_vacation_days | TextInput (numeric) | No | — |
| administrative_days | TextInput (numeric) | No | — |
| has_additional_sundays | Toggle | No | — |
| personal_email | TextInput | No | — |
| phone | TextInput | No | — |
| emergency_contact_name | TextInput | No | — |
| emergency_contact_phone | TextInput | No | — |
| timezone | TimezoneSelect (plugin) | No | — |

**Filters:** `is_active` (TernaryFilter), `is_admin` (TernaryFilter), `premise_id` (SelectFilter, multiple), `position_id` (SelectFilter, multiple)

**Header Actions:** CreateAction

**Row Actions:** ViewAction, EditAction (in ActionGroup)

**Bulk Actions:** DeleteBulkAction

**Related Pages:** `ViewEmployee`, `EditEmployee`, `ManageEmployeeShifts`, `ManageEmployeeDocuments`

**Relation Managers:** None (sub-nav pages used instead)

---

#### CompanyResource
- **File:** `app/Filament/Resources/Companies/CompanyResource.php`
- **Model:** `App\Models\Company`
- **Navigation Group:** `organization`

**Table Columns:** rut, social_reason, business_line, email, country, region (via relation), commune (via relation), address, phone, company_type, is_est, is_active, created_at

**Form Fields:** is_active, rut (with `cl_rut` validation rule), social_reason, business_line, email, region_id (live, cascades commune_id), commune_id (filtered by region), address, phone, company_type, is_est

**Filters:** None

**Header Actions:** CreateAction

**Row Actions:** EditAction

**Bulk Actions:** DeleteBulkAction

**Relation Managers:** `RepresentativesRelationManager` (manages `User` records where `is_legal_rep=true`)

---

#### ShiftResource
- **File:** `app/Filament/Resources/Shifts/ShiftResource.php`
- **Model:** `App\Models\Shift`
- **Navigation Group:** `organization`

**Table Columns:** name (with description tooltip), type (badge), shift_assignments_count (badge), total_week_hours (computed, warns if > 44h), tolerance_in, tolerance_out, is_archive, work_on_holidays, is_default, created_at

**Form Fields (create vs edit differ):**
- Create: name, type, description
- Edit adds: is_archive, is_default, tolerance_in (TimePicker), tolerance_out (TimePicker), work_on_holidays

**Filters:** None

**Header Actions:** CreateAction

**Row Actions:** ViewAction, EditAction (in ActionGroup)

**Bulk Actions:** DeleteBulkAction

**Relation Managers:** `DaysRelationManager` (ShiftDay — inline edit per weekday with hours warnings), `ShiftAssignmentsRelationManager` (ShiftAssignment — user assignments)

---

#### LeaveResource
- **File:** `app/Filament/Resources/Leaves/LeaveResource.php`
- **Model:** `App\Models\Leave`
- **Navigation Group:** `approvals`
- **Navigation Badge:** Count of pending leaves (warning color)

**Table Columns:** user.full_name (AvatarUserName), type (badge), start_date, end_date, half_day (icon), business_days_requested, status (badge), created_at

**Form Fields:**
| Name | Field Type | Required | Notes |
|---|---|---|---|
| user_id | Select | Yes | `belongsTo(User)` |
| type | Select (live) | Yes | Controls status options |
| status | Select | Yes | Medical leaves auto-forced to APPROVED |
| start_date | DatePicker | Yes | — |
| end_date | DatePicker | Yes | — |
| business_days_requested | TextInput (numeric) | Yes | — |
| medical_leave_number | TextInput | Conditional | Shown only for medical type |
| medical_leave_doctor | TextInput | Conditional | Shown only for medical type |
| available_vacation_days | Placeholder | No | Computed read-only display |
| notes | Textarea | No | — |

**Filters:** type (SelectFilter)

**Header Actions:** CreateAction

**Row Actions:** Custom `approve` action (calls `LeaveManager::approve()`), Custom `reject` action (calls `LeaveManager::reject()`), ViewAction, EditAction, DeleteAction (in ActionGroup)

**Bulk Actions:** None

**Widgets:** `LeavesCalendarWidget`

---

#### DocumentResource
- **File:** `app/Filament/Resources/Documents/DocumentResource.php`
- **Model:** `App\Models\Document`
- **Implements:** `HasShieldPermissions` (custom permission prefixes including `publish`, `publish_any`, `sign`)
- **Navigation Group:** `documents`
- **Navigation Badge:** Count of documents pending signature (warning color)

**Table Columns:** title, user.full_name (AvatarUserName), status (badge), type (badge), created_at, updated_at, deleted_at (soft delete)

**Form Fields:**
| Name | Field Type | Required | Notes |
|---|---|---|---|
| title | TextInput | Yes | — |
| type | Select | Yes | — |
| format | Select (live) | Yes | Controls body/file/url visibility |
| status | Select | Yes | — |
| employee_signature_required | Toggle | No | — |
| legal_rep_1_signature_required | Toggle | No | Reveals legal rep 1 fields |
| legal_rep_2_signature_required | Toggle | No | Reveals legal rep 2 fields |
| signature_legal_rep_1_id | Select | Conditional | `belongsTo(User)` where is_legal_rep |
| signature_legal_rep_1_order | Select | Conditional | Signature ordering |
| signature_legal_rep_2_id | Select | Conditional | `belongsTo(User)` where is_legal_rep |
| signature_legal_rep_2_order | Select | Conditional | Signature ordering |
| body | RichEditor | Conditional | Shown when format=HTML; has template-insert action |
| file | SpatieMediaLibraryFileUpload | Conditional | Shown when format=FILE |
| external_url | TextInput | Conditional | Shown when format=LINK |
| published_at | DateTimePicker | No | — |

**Filters:** user_id (SelectFilter), status (SelectFilter), type (SelectFilter)

**Header Actions:** CreateAction

**Row Actions:** ViewAction (large, warning icon — entry point for signing), Custom `publish` (calls `PublishDocument` action), Custom `download` (calls `DownloadDocument` action), EditAction, DeleteAction (with confirmation)

**Bulk Actions:** `publishAny` (authorized), DeleteBulkAction

**Infolist:** Section grid with general info fields

**Relation Managers:** `SignaturesRelationManager` (DocumentSignature — read-only signature status)

**Widgets:** `DocumentActivities` (activity timeline using `jaocero/activity-timeline` package)

---

#### WorkdayResource
- **File:** `app/Filament/Resources/Workdays/WorkdayResource.php`
- **Model:** `App\Models\Workday`

**Table Columns:**
| Name | Type | Notes |
|---|---|---|
| date | TextColumn | Formatted |
| user.full_name | Custom AvatarUserName | — |
| status | BadgeColumn | Color per WorkdayStatus enum |
| mark_in_at | Custom state | Shows pending mod warning icon if pending modification exists |
| mark_out_at | Custom state | Same as above |
| shift.name | TextColumn | — |
| mark_modifications_exists | IconColumn | Yes/No |
| worked_time | TextColumn | — |
| extra_time | TextColumn | — |
| missing_time | TextColumn | — |
| premise.name | TextColumn | — |
| created_at | TextColumn | — |

**Filters:** user (SelectFilter), date (DateRangeFilter from plugin)

**Header Actions:** None

**Row Actions:** Custom `Edit workday` modal action (TimePicker for mark_in/out, reason, notes → calls `MarkModificationManager::modifyFromWorkday()`), ViewAction

**Bulk Actions:** Custom `edit` bulk action (same modal form, calls `MarkModificationManager::bulkModify()`)

**Pagination:** 10 or 25 per page; current-page selection only

**Widgets:** `PendingMarkModifications` (TableWidget — shows employee their own pending modifications with approve/decline actions)

---

#### DocumentTemplateResource
- **File:** `app/Filament/Resources/DocumentTemplates/DocumentTemplateResource.php`
- **Model:** `App\Models\DocumentTemplate`
- **Navigation Group:** `documents`

**Table Columns:** name, is_active (toggle), category, created_at

**Form Fields:** name, body (RichEditor), is_active, category

**Pages:** ListDocumentTemplates, CreateDocumentTemplate, EditDocumentTemplate

---

#### PremiseResource
- **File:** `app/Filament/Resources/Premises/PremiseResource.php`
- **Model:** `App\Models\Premise`
- **Navigation Group:** `organization`

**Table Columns:** name, company.social_reason, address, phone, created_at

**Form Fields:** name, company_id (Select → `belongsTo(Company)`), location (Google Maps field via `cheesegrits/filament-google-maps`), address, phone

**Pages:** ListPremises, CreatePremise, EditPremise, ViewPremise

> **⚠ No direct React/shadcn equivalent for the Google Maps form field.** Requires a custom React component (e.g., `@vis.gl/react-google-maps` or Leaflet).

---

#### PositionResource
- **File:** `app/Filament/Resources/Positions/PositionResource.php`
- **Model:** `App\Models\Position`
- **Navigation Group:** `organization`

**Table Columns:** name, users_count (badge), created_at

**Form Fields:** name, description

**Relation Managers:** `UsersRelationManager` (read-only list of users in the position)

**Pages:** ManagePositions, ViewPosition

---

#### HolidayResource
- **File:** `app/Filament/Resources/Holidays/HolidayResource.php`
- **Model:** `App\Models\Holiday`
- **Navigation Group:** `settings` (collapsed)

**Table Columns:** name, date, mandatory (icon), description

**Form Fields:** name, date (DatePicker), mandatory (Toggle), description

**Pages:** ManageHolidays (single-page manage)

---

### DT Panel (`app/Filament/Dt/Resources/`)

---

#### Dt\DocumentResource
- **File:** `app/Filament/Dt/Resources/Documents/DocumentResource.php`
- **Model:** `App\Models\Document`
- **Purpose:** Read-only document listing for DT auditors
- **Pages:** ManageDocuments

---

#### Dt\IncidentResource
- **File:** `app/Filament/Dt/Resources/Incidents/IncidentResource.php`
- **Model:** `App\Models\Incident`
- **Purpose:** Incident log for DT audit
- **Pages:** ManageIncidents

---

#### Dt\OrganizationResource
- **File:** `app/Filament/Dt/Resources/Organizations/OrganizationResource.php`
- **Model:** `App\Models\Organization`
- **Purpose:** View the currently selected organization during DT audit session
- **Pages:** ManageOrganizations

---

### SaaS Panel (`app/Filament/Saas/Resources/`)

---

#### Saas\OrganizationResource
- **File:** `app/Filament/Saas/Resources/Organizations/OrganizationResource.php`
- **Model:** `App\Models\Organization`
- **Purpose:** Platform-level organization creation and management
- **Pages:** ListOrganizations, CreateOrganization, EditOrganization

---

#### Saas\UserResource
- **File:** `app/Filament/Saas/Resources/Users/UserResource.php`
- **Model:** `App\Models\User`
- **Purpose:** Platform-level system user management
- **Pages:** ListUsers, CreateUser, EditUser

---

#### Saas\DocumentVarResource
- **File:** `app/Filament/Saas/Resources/DocumentVars/DocumentVarResource.php`
- **Model:** `App\Models\DocumentVar`
- **Purpose:** Global document template variable definitions
- **Pages:** ManageDocumentVars

---

## 3. Custom Filament Pages & Widgets

### Custom Pages (not auto-generated resource pages)

| File | Panel | Title | Purpose | Complexity |
|---|---|---|---|---|
| `app/Filament/Pages/Auth/Login.php` | Admin | Login | Custom login page | Low |
| `app/Filament/Pages/Auth/Register.php` | Admin | Register | Custom registration | Low |
| `app/Filament/Pages/Auth/EditProfile.php` | Admin | Edit Profile | Profile with avatar upload | Medium |
| `app/Filament/Pages/Reports/Reports.php` | Admin | Reportes | Placeholder (currently disabled, `canAccess()=false`) | Low |
| `app/Filament/Pages/Settings/GeneralSettings.php` | Admin | Configuración General | Loads `Setting` model, renders Livewire `EditGeneralSettings` component | Medium |
| `app/Filament/Pages/MarkModification/ReviewConfirmation.php` | Admin | (none) | **Public page (no auth middleware)** — receives `?ulid=&review=approve\|decline` URL params, calls `MarkModificationManager::approve/decline()`, displays result message | Medium |
| `app/Filament/Dt/Pages/Auth/Login.php` | DT | Login | DT-specific login | Low |
| `app/Filament/Dt/Pages/Auth/PasswordReset/RequestPasswordReset.php` | DT | Password Reset | DT password reset | Low |
| `app/Filament/Dt/Pages/ValidateMark.php` | DT | Validar marca | Form + Infolist: looks up mark by checksum and displays its details | Medium |
| `app/Filament/Dt/Pages/Reports/DtReports.php` | DT | (abstract base) | Base class for all DT report pages — provides header actions (preview, download Excel/PDF/Word), dispatches Livewire events to `Filters` component | High |
| `app/Filament/Dt/Pages/Reports/AttendanceReport.php` | DT | Reporte Asistencia | Extends DtReports | High |
| `app/Filament/Dt/Pages/Reports/DailyReport.php` | DT | Reporte Jornada Diaria | Extends DtReports | High |
| `app/Filament/Dt/Pages/Reports/ShiftChangesReport.php` | DT | Modificaciones de Turnos | Extends DtReports | High |
| `app/Filament/Dt/Pages/Reports/SundaysReport.php` | DT | Domingos y festivos | Extends DtReports | High |
| `app/Filament/Saas/Pages/DtAuditLog.php` | SaaS | DT Audit Log | Table page querying activity log, with export action (`DtAuditLogExporter`) | Medium |

> **⚠ DT Report Pages are High complexity.** Each report page embeds multiple Livewire components (`Filters`, `Preview`, one of `AttendanceTable`, `DailyTable`, `ShiftChangesTable`, `SundaysTable`, `IncidentsTable`) coordinated via Livewire events. The `Filters` component itself combines a Filament Form, a Filament Table, and actions all in one Livewire component. In React/Inertia this will need a purpose-built filter UI + data-table + export pipeline.

### Livewire Components Embedded in Filament Pages

These are standalone Livewire components rendered inside Filament Blade views — they have **no direct Filament equivalent** and must be rewritten as React components.

| File | Used In | Purpose |
|---|---|---|
| `app/Livewire/Reports/Filters.php` | DT Report pages | Master filter component: Filament Form + Filament Table (employee selector) + download/preview actions. Dispatches events to Preview and table components. |
| `app/Livewire/Reports/Preview.php` | DT Report pages | Receives `show-preview` event, renders report preview |
| `app/Livewire/Reports/AttendanceTable.php` | AttendanceReport page | Displays attendance report data |
| `app/Livewire/Reports/DailyTable.php` | DailyReport page | Displays daily workday data |
| `app/Livewire/Reports/ShiftChangesTable.php` | ShiftChangesReport page | Displays shift change data |
| `app/Livewire/Reports/SundaysTable.php` | SundaysReport page | Displays Sunday/holiday work data |
| `app/Livewire/Reports/IncidentsTable.php` | (Incidents report) | Displays incident data |
| `app/Livewire/Reports/DtReportQueryBuilder.php` | DT Report pages | Query builder for report data |
| `app/Livewire/Settings/EditGeneralSettings.php` | GeneralSettings page | Form for editing `Setting` model |
| `app/Livewire/Documents/ListDocumentVars.php` | Document creation | Displays available template variables |
| `app/Livewire/Marks/MarkCard.php` | AddMark widget view | Card display for a single mark record |
| `app/Livewire/Activity/ActivityCauser.php` | DocumentActivities widget | Renders the causer (user) in activity timeline |

### Widgets

| File | Panel | Parent Class | Data Source | Polling | Interactivity |
|---|---|---|---|---|---|
| `app/Filament/Widgets/AddMark.php` | Admin (dashboard) | `Filament\Widgets\Widget` | `MarkManager::getTodayMark()`, `getShiftForToday()` | None | Yes — Check-in / Check-out actions with confirmation |
| `app/Filament/Resources/Leaves/Widgets/LeavesCalendarWidget.php` | Admin (Leaves index) | `FullCalendarWidget` (saade plugin) | `Leave::query()` filtered by date range, APPROVED+PENDING | None (date-range driven) | No (ViewAction disabled) |
| `app/Filament/Resources/Workdays/Widgets/PendingMarkModifications.php` | Admin (Workdays index) | `TableWidget` | `MarkModification::query()` where status=PENDING & user=auth | None | Yes — per-row Approve/Decline actions |
| `app/Filament/Resources/Documents/Widgets/DocumentActivities.php` | Admin (Document view) | `Widget` | `$record->activities()` (Spatie activitylog) | None | No — read-only timeline |

> **⚠ Filament-specific widget features with no direct React equivalent:**
> - `LeavesCalendarWidget` uses `saade/filament-fullcalendar` — needs FullCalendar.js wired to an API endpoint in React.
> - `DocumentActivities` uses `jaocero/activity-timeline` — needs a custom timeline component.
> - `AddMark` / `PendingMarkModifications` use Filament's action+notification system inline — needs dedicated React components with toast notifications.

---

## 4. Authentication & Authorization

### Auth Guards & Providers

| Panel | Guard | User Provider | Restriction |
|---|---|---|---|
| Admin (`/admin`) | `web` | `users` (Eloquent `User`) | `User::canAccessPanel()` — must have `is_admin=true` or be super_admin role |
| DT (`/dt`) | `web` | `users` (Eloquent `User`) | `User::canAccessPanel()` — must have `is_dt=true` AND have an active (non-expired) password |
| SaaS (`/saas`) | `web` | `users` (Eloquent `User`) | `User::canAccessPanel()` — email must equal `super_admin@example.com` |

### Middleware

- **Admin panel:** Standard Filament middleware stack (cookie encryption, start session, share errors, CSRF, bind routes, authenticate, disable cache, dispatch events)
- **DT panel:** Same stack; additional DT-specific login page for `PasswordExpires` check
- **SaaS panel:** Same stack
- **`ReviewConfirmation` page:** Explicitly bypasses `auth` middleware — publicly accessible via signed URL with `?ulid=&review=` params

### Multi-Tenancy Session Scoping

`SetOrganizationIdInSession` listener fires on Laravel's `Login` event, writing `organization_id` to the session. `OrganizationScope` global scope reads this from the session on every query to automatically filter organization-scoped models. The DT panel uses a separate session key (`DT_ORGANIZATION_ID`) set when an auditor selects an organization to inspect.

### Roles & Permissions — Filament Shield

- **Package:** `bezhansalleh/filament-shield: ^4.0`
- **Backed by:** `spatie/laravel-permission` (via `HasRoles` trait on `User`)
- **Permission format:** `{resource}:{ability}` e.g. `document:view_any`, `document:publish`
- **Super admin:** Auto-granted, bypasses all policy checks
- **Custom permissions:** `DocumentResource` adds `publish`, `publish_any`, `sign` beyond standard CRUD
- **Config:** `config/filament-shield.php`

### Policies

| Policy | Model | Abilities |
|---|---|---|
| `CompanyPolicy` | `Company` | viewAny, view, create, update, delete, restore, forceDelete, replicate, reorder |
| `DocumentPolicy` | `Document` | viewAny, view, create, update, delete, restore, forceDelete, replicate, reorder, **publish**, **sign** |
| `DocumentTemplatePolicy` | `DocumentTemplate` | Standard CRUD |
| `HolidayPolicy` | `Holiday` | Standard CRUD |
| `LeavePolicy` | `Leave` | Standard CRUD |
| `MarkPolicy` | `Mark` | Standard CRUD |
| `PositionPolicy` | `Position` | Standard CRUD |
| `PremisePolicy` | `Premise` | Standard CRUD |
| `RolePolicy` | Spatie `Role` | viewAny, view, create, update, delete (for Shield role management) |
| `ShiftPolicy` | `Shift` | Standard CRUD |
| `UserPolicy` | `User` | Standard CRUD |
| `WorkdayPolicy` | `Workday` | Standard CRUD |

> All policies are auto-discovered by Laravel and integrated into Filament's authorization layer via `Gate::allows()`.

---

## 5. Backend Logic to PRESERVE (do not touch in migration)

These files contain business logic independent of Filament and must not be modified during the UI migration.

### Managers

| File | Description |
|---|---|
| `app/Managers/MarkManager.php` | Creates clock-in/clock-out marks; resolves shift assignment and timezone for the user's current mark |
| `app/Managers/LeaveManager.php` | Approves/rejects leaves; handles vacation balance deduction/refund; logs activity |
| `app/Managers/MarkModificationManager.php` | Creates, approves, declines mark corrections; auto-approves overdue ones; bulk modify; creates modifications from workday edits |

### Services

| File | Description |
|---|---|
| `app/Services/TimeZoneService.php` | UTC ↔ America/Santiago conversions for user-contextualized marks |
| `app/Services/WorkdayCalculator.php` | Core algorithm: determines WorkdayStatus from marks + shifts + leaves; calculates worked/extra/missing time |
| `app/Services/OrganizationSettings.php` | Retrieves per-organization configuration |
| `app/Services/Documents/DocumentVariableParser.php` | Parses and substitutes template variables in document body |
| `app/Services/Documents/PdfSigner.php` | Handles digital PDF signing workflow |
| `app/Services/Reports/DtAttendanceReport.php` | Generates government attendance report data |
| `app/Services/Reports/DtDailyReport.php` | Generates daily workday report data |
| `app/Services/Reports/DtIncidentsReport.php` | Generates incident report data |
| `app/Services/Reports/DtQueryReport.php` | Query-based report data assembly |
| `app/Services/Reports/DtShiftChangesReport.php` | Generates shift modification report data |
| `app/Services/Reports/DtSundaysReport.php` | Generates Sunday/holiday work report data |
| `app/Services/Reports/ShiftChangeLineItem.php` | Data transfer object for shift change line items |

### Model Observers

| File | Model | Hooks | Side Effects |
|---|---|---|---|
| `app/Observers/MarkObserver.php` | `Mark` | `creating`, `created` | `creating`: sets premise snapshot, employee/employer RUT/name, datetime, checksum; `created`: sends `MarkCreated` email notification |
| `app/Observers/LeaveObserver.php` | `Leave` | `creating`, `saving`, `created`, `updated`, `deleted`, `restored`, `forceDeleted` | `creating`: sets `created_by`; `saving`: forces APPROVED for medical leaves; `created/updated/deleted/restored`: dispatches `WorkdaysRecalculationNeeded` event |
| `app/Observers/UserObserver.php` | `User` | `updated` | Sends `AuthProfileUpdated` notification if email, password, or personal_email changed |
| `app/Observers/DocumentObserver.php` | `Document` | (inferred: created/updated/deleted) | Activity logging for document lifecycle events |
| `app/Observers/OrganizationObserver.php` | `Organization` | (inferred) | Organization lifecycle hooks |
| `app/Observers/SettingObserver.php` | `Setting` | (inferred) | Settings change side effects |
| `app/Observers/ShiftObserver.php` | `Shift` | (inferred) | Enforces single default shift per organization |
| `app/Observers/ShiftDayObserver.php` | `ShiftDay` | (inferred) | Recalculates `total_work_hours` from time fields |
| `app/Observers/ShiftAssignmentObserver.php` | `ShiftAssignment` | (inferred) | Workday recalculation on assignment change |

### Events & Listeners

| File | Type | Description |
|---|---|---|
| `app/Events/WorkdaysRecalculationNeeded.php` | Event | Carries `$userIds`, `$startDate`, `$endDate`; dispatched when leaves change |
| `app/Listeners/RecalculateWorkdaysListener.php` | Listener (ShouldQueue) | Handles `WorkdaysRecalculationNeeded`; iterates workdays and calls `WorkdayCalculator::recalculateWorkday()` |
| `app/Listeners/SetOrganizationIdInSession.php` | Listener | Handles `Login`; writes `organization_id` to session for `OrganizationScope` |
| `app/Listeners/RemoveOrganizationIdFromSession.php` | Listener | Handles `Logout`; clears `organization_id` from session |

### Console Commands & Schedule

| File | Signature | Schedule | Description |
|---|---|---|---|
| `app/Console/Commands/CalculateWorkday.php` | `app:calculate-workday` | Daily at 02:00 America/Santiago | Calculates workdays for previous day; adds missing mark modifications; auto-approves tolerance-based corrections |
| `app/Console/Commands/ApproveOverdueModifications.php` | `app:approve-overdue-mark-modifications` | Every 10 minutes | Calls `MarkModificationManager::approveOverdueModifications()` |
| `app/Console/Commands/SendMissingMarkEmails.php` | `app:send-missing-mark-emails` | (inferred: frequent) | Sends notifications to employees/employers for missing marks after 31 minutes |
| `app/Console/Commands/UpdateChileanHolidays.php` | `app:update-chilean-holidays` | (inferred: annual/manual) | Web-scrapes Chilean holidays via `roach-php` and updates the `holidays` table |
| `app/Console/Commands/SeedChileanRegions.php` | (seed command) | Manual | Seeds Chilean regions and communes into the database |

### Standalone Business Logic Actions

| File | Type | Description |
|---|---|---|
| `app/Actions/ActionResponse.php` | Response DTO | Wraps action results (success/failure/message) |
| `app/Actions/Document/CreateDocument.php` | Business Action | Persists a new Document record |
| `app/Actions/Document/CreateDocumentSignatures.php` | Business Action | Creates `DocumentSignature` records for all required signatories after document publish |
| `app/Actions/Document/DownloadDocument.php` | Business Action | Resolves and streams the document file |
| `app/Actions/Document/PublishDocument.php` | Business Action | Sets document to PUBLISHED, calls `CreateDocumentSignatures`, sends notifications; also handles bulk publish |
| `app/Actions/Document/RejectDocumentSignature.php` | Business Action | Records a signatory's rejection |
| `app/Actions/Document/SendVerificationCode.php` | Business Action | Generates and sends a one-time verification code for digital signing |
| `app/Actions/Document/SignDocument.php` | Business Action | Validates verification code and records the signature |

---

## 6. External API Integrations

| Service / Package | Package | Module Owner | Used In | Notes |
|---|---|---|---|---|
| **Google Maps** | `cheesegrits/filament-google-maps` | Premises | Filament Form UI | Map picker for lat/lng on `PremiseResource`; `Premise` model has `getComputedLocation()` method returning map-compatible data |
| **Google Static Maps** | `mastani/laravel-google-static-map` | Marks / Employee view | Views | Static map image generation from coordinates |
| **Excel export** | `maatwebsite/excel` | DT Reports | Jobs + DT Report pages | Generates `.xlsx` attendance/workday exports |
| **Word export** | `phpoffice/phpword` | DT Reports | DT Report pages | Generates `.docx` reports |
| **PDF generation** | `barryvdh/laravel-dompdf` + `dompdf/dompdf` | Documents + DT Reports | Filament Actions + DT Report pages | PDF document generation and report export |
| **PDF signing** | `setasign/fpdf` + `setasign/fpdi` | Documents | `PdfSigner` service | Digital signature overlay on PDF files |
| **Web scraping** | `roach-php/core` + `roach-php/laravel` | Holidays | Console Command | Scrapes Chilean holiday data from government website |
| **Activity log** | `spatie/laravel-activitylog` | Documents, Leaves, Marks | Observers + Filament Widgets | All significant events logged; displayed in `DocumentActivities` widget |
| **Media library** | `filament/spatie-laravel-media-library-plugin` | Employees, Documents | Filament Forms | Avatar upload for users, file attachment for documents |
| **Queue / Horizon** | `laravel/horizon` | All async work | Background | Redis-backed queue dashboard for `RecalculateWorkdaysListener` and any future jobs |
| **Sanctum** | `laravel/sanctum` | API (marks) | API routes | Token-based API auth for `POST /api/marks` (mobile check-in app) |
| **Telescope** | `laravel/telescope` | Dev/ops | Background | Request/query debugging; pruned daily by schedule |
| **Chilean RUT** | `freshwork/chilean-bundle` | Employees, Companies | Forms + Traits | RUT validation, formatting via `FormatedRut` trait |

---

## 7. Database Relationships Relevant to UI

### User (Employee)
- `belongsTo` Company, Premise, Position, Organization
- Self-referential `belongsTo(User, 'supervisor_id')`
- `hasMany` ShiftAssignment, Leave, Document, DocumentSignature
- `belongsToMany` Shift (via `shift_assignments` pivot)
- `hasOne` Setting
- **Used in forms:** company_id, premise_id, position_id, supervisor_id all appear as Select fields in EmployeeResource
- **Pivot:** `shift_assignments` has extra attributes: `start_date`, `end_date`, `notification_date`, `is_permanent`, `requested_by_employee`

### Mark
- `belongsTo` User, Premise, Shift
- `hasMany` MarkModification
- `belongsTo(MarkModification, 'last_mark_modification_id')`
- **Used in forms:** Workday edit modal resolves mark_in/mark_out by workday relation

### Workday
- `belongsTo` User, Premise, Shift, Leave
- `hasOne(Mark, 'id', 'mark_in_id')` and `hasOne(Mark, 'id', 'mark_out_id')` — custom FK
- `hasMany` MarkModification (scoped: pending, pendingMarkIn, pendingMarkOut)
- **Used in forms:** WorkdayResource modify action reads/writes through these relationships

### Leave
- `belongsTo` User
- `hasMany` (inferred via Workday.leave_id)
- **Used in forms:** user_id Select in LeaveResource

### Document
- `belongsTo` User (creator)
- `belongsTo(User, 'signature_legal_rep_1_id')` and `belongsTo(User, 'signature_legal_rep_2_id')`
- `hasMany` DocumentSignature
- **Used in forms:** user_id, signature_legal_rep_1_id, signature_legal_rep_2_id all as Select fields; SignaturesRelationManager for read-only display

### Company
- `belongsTo` Commune, Region
- `hasMany` User (representatives where `is_legal_rep=true`), Premise
- **Used in forms:** RepresentativesRelationManager creates Users directly via Company context

### Shift
- `hasMany` ShiftDay
- `belongsToMany(User)` via `shift_assignments` pivot (with extra attributes)
- `hasMany` ShiftAssignment
- **Used in forms:** DaysRelationManager (inline per-day editing), ShiftAssignmentsRelationManager

### ShiftAssignment (pivot with extras)
- `belongsTo` Shift, User
- Extra attributes on pivot: `start_date`, `end_date`, `notification_date`, `is_permanent`, `requested_by_employee`
- **Important for React:** The ManageEmployeeShifts page manages these — in React this needs a custom form that handles dated ranges, not a simple checkbox list

---

## 8. Shared/Global Filament Configuration

### Panel Providers

| Setting | Admin | DT | SaaS |
|---|---|---|---|
| Path | `/admin` | `/dt` | `/saas` |
| Primary color | Indigo | Amber | Default |
| Theme file | `resources/css/filament/admin/theme.css` | `resources/css/filament/dt/theme.css` | Default |
| SPA mode | Yes | No | No |
| Top navigation | No (sidebar) | Yes | No |
| Collapsible sidebar | Yes | N/A | No |
| Max content width | Default | Full | Full |
| Database notifications | No | No | Yes |
| Unsaved changes alert | Yes | No | No |

### Filament Plugins Installed

| Package | Plugin Class | What It Adds |
|---|---|---|
| `bezhansalleh/filament-shield` | `FilamentShieldPlugin` | Role/permission UI, `RoleResource`, shield-generated policies |
| `saade/filament-fullcalendar` | `FilamentFullCalendarPlugin` | `LeavesCalendarWidget` — calendar with locale `es`, `America/Santiago` timezone, multi-month view |
| `cheesegrits/filament-google-maps` | (auto-registered) | Google Maps form field for `PremiseResource` |
| `malzariey/filament-daterangepicker-filter` | (auto-registered) | `DateRangeFilter` used in WorkdayResource and `DateRangePicker` field in Reports Filters |
| `tapp/filament-timezone-field` | (auto-registered) | `TimezoneSelect` field in EmployeeResource |
| `filament/spatie-laravel-media-library-plugin` | (auto-registered) | `SpatieMediaLibraryFileUpload` in EmployeeResource and DocumentResource |
| `jaocero/activity-timeline` | (auto-registered) | Activity timeline components in `DocumentActivities` widget |

### Navigation Groups (Admin Panel)

| Group Key | Translated Label | Resources / Pages |
|---|---|---|
| `organization` | Organización | Employees, Companies, Premises, Positions, Shifts |
| `approvals` | Aprobaciones | Leaves |
| `documents` | Documentos | Documents, DocumentTemplates |
| `settings` | Configuración (collapsed) | Holidays, GeneralSettings |

### Exports

| File | Exporter | Used In |
|---|---|---|
| `app/Filament/Exports/LeaveExporter.php` | `LeaveExporter` | LeaveResource (inferred) |
| `app/Filament/Exports/DtAuditLogExporter.php` | `DtAuditLogExporter` | SaaS DtAuditLog page |
| `app/Filament/Exports/DocumentVarExporter.php` | `DocumentVarExporter` | DocumentVars (inferred) |

### Base Resource

`app/Filament/Resources/Resource.php` — Custom base resource class. All Admin resources extend this instead of `Filament\Resources\Resource`. Check for shared logic (e.g., default navigation, common form fields).

---

## 9. Migration Risk Assessment

| Resource / Feature | Complexity | Reason | Suggested Order |
|---|---|---|---|
| **Auth (login, register, profile)** | Low | Standard form pages; translate to Inertia auth pages | 1st (foundation) |
| **Roles & Permissions (Shield)** | Medium | Shield generates UI; need React role management UI backed by same Spatie permission tables | 2nd (foundation) |
| **Organizations (SaaS)** | Low | Simple CRUD, no complex relations | 3rd |
| **Holidays** | Low | Simple CRUD, single model | 4th |
| **Positions** | Low | Simple CRUD + read-only relation list | 5th |
| **Companies** | Medium | Cascading Region→Commune selects; RepresentativesRelationManager creates Users inline | 6th |
| **Premises** | Medium | Google Maps field has no shadcn equivalent — needs custom map picker component | 7th |
| **Shifts** | Medium | Form splits create/edit; DaysRelationManager edits 7 ShiftDay rows in table; ShiftAssignmentsRelationManager | 8th |
| **Employees** | Medium | Multi-tab form (5 tabs), avatar upload, 5 related selects, sub-navigation with ManageShifts and ManageDocuments sub-pages | 9th |
| **Leaves** | Medium | Conditional form fields by leave type; approve/reject actions backed by LeaveManager; FullCalendar widget | 10th |
| **General Settings** | Medium | Livewire component inside Filament page; needs React equivalent | 11th |
| **Mark Modification Review (public page)** | Medium | Public URL with ULID token, no auth — maps to an Inertia guest page | 12th |
| **Workdays** | High | Custom state columns showing pending modifications; bulk edit modal with MarkModificationManager; PendingMarkModifications widget with inline approve/decline | 13th |
| **Documents** | High | Multi-format form (HTML/FILE/LINK) with conditional fields; signature workflow with two legal reps; RichEditor with template variables; DocumentActivities timeline widget (third-party); custom permissions beyond CRUD | 14th |
| **Document Templates** | Medium | RichEditor with variable substitution preview; Livewire `ListDocumentVars` component | 15th |
| **Add Mark Widget (Dashboard)** | Medium | Calls MarkManager; requires shift-aware check-in/check-out with confirmation; Livewire `MarkCard` component | 16th |
| **DT Panel — ValidateMark** | Medium | Form + Infolist on single page; checksum lookup | 17th |
| **DT Panel — Reports** | High | 5 report types; multi-filter Livewire component with embedded Filament Table for employee selection; Excel/PDF/Word export pipeline; Preview vs Download modes; `DtReportExporter` coordinating multiple `Services/Reports/` classes | 18th (last) |
| **DT Audit Log (SaaS)** | Low | Simple table from activity log + export | 16th |

### Key High-Complexity Flags

- **Livewire components inside Filament pages** — All DT Report pages and GeneralSettings embed Livewire components that coordinate via events. In React/Inertia these become component trees with shared state.
- **No React/shadcn equivalent for:** Filament's `FullCalendarWidget`, Google Maps form field, `ActivityTimeline` infolist component, DateRangePicker filter — all need custom React implementations.
- **Filament Notifications** (`Filament\Notifications\Notification::make()...->send()`) are used throughout actions and widgets. In React/Inertia this maps to a toast system (e.g., `sonner`).
- **Global Search** — Admin panel has Filament global search. React will need an Inertia-compatible search endpoint.
- **SPA mode** (Admin panel) — Navigation does not trigger full page reloads in Filament. Inertia handles this natively.
- **Multi-tenancy session scope** — The `OrganizationScope` reads from the session. This logic is 100% backend and survives the migration; Inertia requests carry the same session cookie.

---

## 10. Proposed GitHub Issues List

### Milestone 1 — Foundation

| Issue Title | Complexity | Depends On |
|---|---|---|
| [M1] Set up Laravel React starter kit (Inertia v3, React 19, TypeScript, shadcn/ui) | Low | — |
| [M1] Configure Spatie Laravel Permission integration (preserve existing roles/permissions) | Low | M1: Setup |
| [M1] Build authenticated layout shell (sidebar nav for Admin, top-nav for DT, minimal for SaaS) | Medium | M1: Setup |
| [M1] Implement Admin panel login / register / password-reset pages | Low | M1: Layout shell |
| [M1] Implement DT panel login / password-reset pages (PasswordExpires check) | Low | M1: Layout shell |
| [M1] Implement SaaS panel login page (super admin only) | Low | M1: Layout shell |
| [M1] Implement Edit Profile page (with avatar upload via Spatie Media Library) | Medium | M1: Auth pages |
| [M1] Build role & permission management UI (replacing FilamentShield RoleResource) | Medium | M1: Permission integration |
| [M1] Build reusable toast notification system (replacing Filament Notifications) | Low | M1: Layout shell |
| [M1] Mark Modification Review public page (public Inertia route, no auth, ULID + approve/decline) | Medium | M1: Setup |

### Milestone 2 — Core Resources

| Issue Title | Complexity | Depends On |
|---|---|---|
| [M2] Organizations CRUD (SaaS panel) | Low | M1: SaaS login |
| [M2] Document Variables CRUD (SaaS panel) | Low | M1: SaaS login |
| [M2] DT Audit Log page (SaaS panel) | Low | M1: SaaS login |
| [M2] Holidays CRUD | Low | M1: Admin layout |
| [M2] Positions CRUD with user list | Low | M1: Admin layout |
| [M2] Companies CRUD with cascading Region→Commune selects and Representatives inline creation | Medium | M2: Positions (user select) |
| [M2] Premises CRUD with custom map picker component (Google Maps / Leaflet) | Medium | M2: Companies |
| [M2] Shifts CRUD with per-day configuration table and weekly hours validation | Medium | M1: Admin layout |
| [M2] Shift Assignments management (employee→shift with date range) | Medium | M2: Shifts, M2: Employees |
| [M2] Employees CRUD (multi-tab form, avatar upload, sub-pages: Shifts, Documents) | Medium | M2: Companies, Premises, Positions, Shifts |
| [M2] Leaves CRUD with approve/reject actions and vacation balance display | Medium | M2: Employees |
| [M2] DT — Validate Mark page (checksum lookup + infolist result) | Medium | M1: DT layout |
| [M2] DT — Documents read-only list | Low | M1: DT layout |
| [M2] DT — Incidents list | Low | M1: DT layout |
| [M2] DT — Organization selector (for audit session scope) | Low | M1: DT layout |

### Milestone 3 — Complex Features

| Issue Title | Complexity | Depends On |
|---|---|---|
| [M3] Workdays list with status badges, pending modification indicators, and bulk edit modal | High | M2: Employees, M2: Shifts |
| [M3] Workday modify action (mark-in/out TimePicker, reason, MarkModificationManager) | High | M3: Workdays list |
| [M3] PendingMarkModifications widget on Workdays page (inline approve/decline) | Medium | M3: Workday modify |
| [M3] Dashboard Add Mark widget (shift-aware check-in/check-out with confirmation) | Medium | M2: Shifts |
| [M3] Leaves FullCalendar widget (React FullCalendar.js backed by API endpoint) | Medium | M2: Leaves |
| [M3] Documents CRUD (multi-format form, conditional signature fields, RichEditor with template vars) | High | M2: Employees, M3: Document Templates |
| [M3] Document Templates CRUD (RichEditor + variable list panel) | Medium | M1: Admin layout |
| [M3] Document publish + download actions (PublishDocument, DownloadDocument actions) | Medium | M3: Documents |
| [M3] Document Signatures relation panel (read-only status display on document view) | Medium | M3: Documents |
| [M3] Document Activities timeline widget (custom timeline component, Spatie activity log) | Medium | M3: Documents |
| [M3] General Settings page (notification toggles for missing marks, document settings) | Medium | M1: Admin layout |
| [M3] DT Reports — Filter UI (report type, date range, multi-select employees/positions/premises) | High | M2: DT layout |
| [M3] DT Reports — Attendance report table + preview | High | M3: DT Filter UI |
| [M3] DT Reports — Daily workday report table + preview | High | M3: DT Filter UI |
| [M3] DT Reports — Shift changes report table + preview | High | M3: DT Filter UI |
| [M3] DT Reports — Sundays/holidays report table + preview | High | M3: DT Filter UI |
| [M3] DT Reports — Incidents report table + preview | High | M3: DT Filter UI |
| [M3] DT Reports — Excel / PDF / Word export pipeline | High | M3: All DT reports |

### Milestone 4 — Polish & Testing

| Issue Title | Complexity | Depends On |
|---|---|---|
| [M4] Spanish i18n for all UI strings (replace Filament's `__()` calls with React i18n) | Medium | All M2+M3 issues |
| [M4] Chilean RUT formatting in all form inputs and display columns | Low | All M2+M3 issues |
| [M4] Timezone-aware display for all date/time fields (America/Santiago) | Low | All M2+M3 issues |
| [M4] Multi-tenancy organization switcher / session scope verification | Medium | M1: Auth |
| [M4] API endpoint for mobile mark creation (preserve existing Sanctum API routes) | Low | M2: Marks/Workdays |
| [M4] Feature tests for all CRUD resources (Pest + Inertia test helpers) | Medium | All M2+M3 issues |
| [M4] Feature tests for document signature workflow | High | M3: Documents |
| [M4] Feature tests for workday calculation and mark modification workflow | High | M3: Workdays |
| [M4] Feature tests for leave approval/rejection and vacation balance | Medium | M2: Leaves |
| [M4] End-to-end smoke tests for DT audit session flow | High | M3: DT Reports |
| [M4] Performance review: N+1 queries in resource tables (eager loading audit) | Medium | All M2+M3 issues |
| [M4] Accessibility and responsive layout review | Low | M4: i18n |
