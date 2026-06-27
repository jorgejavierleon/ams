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

Organization-scoped via the `BelongsToOrganization` trait. All models belonging to an org must use this trait — it scopes queries to the current org in session. Never bypass this scope on org-owned models.

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

## Old App Reference

When implementing a feature, always check `../ams-filament` first:
- `app/Managers/` — MarkManager, LeaveManager, WorkdayCalculator (reuse, don't reimplement)
- `app/Observers/` — model observers (copy, don't rewrite)
- `app/Models/` — source of truth for model structure and relationships
- `database/seeders/` — reference for seed data and roles
