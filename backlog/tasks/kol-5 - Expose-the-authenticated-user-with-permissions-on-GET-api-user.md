---
id: KOL-5
title: Expose the authenticated user with permissions on GET /api/user
status: Done
assignee: []
created_date: '2026-08-02 11:29'
updated_date: '2026-08-02 11:55'
labels: []
dependencies: []
documentation:
  - docs/prd-mobile-app.md
ordinal: 4000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The mobile app (kolvi-mobile, task KMO-8 "Login screen and token acquisition") signs in against `POST /api/sanctum/token` and then calls `GET /api/user` to load the signed-in employee. Two problems block it today.

1. The route returns the raw Eloquent model (`fn (Request $request) => $request->user()`), so every column plus the `avatar` accessor goes over the wire. The mobile client has to whitelist fields defensively, and any new column silently becomes part of a public API contract.
2. The payload carries no permissions at all. The mobile app must gate features on permission names rather than role names (a hard acceptance criterion on KMO-8, and it mirrors how `routes/api.php` already guards the mark routes with `permission:ClockOwn:Mark` / `permission:ViewOwn:Mark`). Without permissions in the payload the app cannot decide what to show, and KMO-8 ships with that criterion open.

Add a `UserResource` (same pattern as the existing `App\Http\Resources\MarkResource`) and return it from `GET /api/user`.

### Contract the mobile client expects

```json
{
  "id": 3,
  "name": "Empleado Demo",
  "first_name": "Empleado",
  "last_name": "Demo",
  "rut": "21437581-8",
  "email": "employee@example.com",
  "avatar": "https://.../avatar.jpg",
  "permissions": [
    "ClockOwn:Mark",
    "ViewOwn:Mark",
    "ViewOwn:Workday",
    "RequestOwn:Leave",
    "ViewOwn:Leave",
    "CancelOwn:Leave",
    "ReviewOwn:MarkModification",
    "ViewOwn:Document",
    "SignOwn:Document"
  ]
}
```

Notes on the shape:

- `permissions` should be the effective set, i.e. direct permissions plus everything inherited from the user roles (`$user->getAllPermissions()->pluck("name")`). The app never sees role names and must not have to infer them.
- A flat array of strings is preferred. The mobile parser also tolerates `[{"name": "ClockOwn:Mark"}]` (the raw Spatie shape) so a resource collection would not break it, but flat strings keep the payload small.
- `rut` unformatted is fine; the app formats it with its own `es-CL` formatter. If the `formatted_rut` accessor is cheaper to expose, say so on the ticket and the app will adapt.
- Nullable fields (`first_name`, `last_name`, `rut`, `avatar`) may be `null`; the app already handles that. `permissions` must never be `null` or absent.
- Nothing else about the endpoint changes: still `GET /api/user`, still behind `auth:sanctum`, still outside any `/api/v1` prefix.

### Follow-up on the mobile side

kolvi-mobile KMO-8 ships the client half now: a `Permission` union built from `RoleSeeder::EMPLOYEE_PERMISSIONS`, a tolerant parser, and a `can(permission)` gate that fails closed when the field is missing. Once this ticket is done, KMO-8 verifies its permissions criterion against the real payload and closes it.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 GET /api/user returns a UserResource, not the raw model, and exposes only id, name, first_name, last_name, rut, email, avatar and permissions
- [x] #2 The response never includes password, two_factor_secret, two_factor_recovery_codes, remember_token or unrelated internal columns
- [x] #3 permissions is a flat array of permission-name strings covering both direct permissions and those inherited from the user roles
- [x] #4 A user with no roles and no permissions receives permissions as an empty array, never null and never absent
- [x] #5 The endpoint stays behind auth:sanctum and an unauthenticated request still gets 401
- [x] #6 A Pest feature test covers a seeded employee (the nine EMPLOYEE_PERMISSIONS) and a user with no permissions
<!-- AC:END -->

## Definition of Done
<!-- DOD:BEGIN -->
- [x] #1 vendor/bin/pint --dirty --format agent reports clean
- [x] #2 sa test --compact passes
- [x] #3 npm run types:check passes when TypeScript touched
- [x] #4 Every PHP change has a Pest test
<!-- DOD:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Add App\Http\Resources\UserResource exposing id, name, first_name, last_name, rut, email, avatar and a flat permissions array from getAllPermissions()->pluck('name').
2. Point GET /api/user at the resource (keep it inside the auth:sanctum group, no /api/v1 prefix).
3. Add tests/Feature/Api/UserApiTest.php covering: seeded employee gets the nine EMPLOYEE_PERMISSIONS, a user with no roles gets [], sensitive columns absent, 401 unauthenticated.
4. pint --dirty + sa test --compact.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented UserResource (app/Http/Resources/UserResource.php) and pointed GET /api/user at it in routes/api.php. Resource wrapping is already globally disabled in AppServiceProvider, so the payload stays top-level (no "data" key) like MarkResource. Permissions come from getAllPermissions()->pluck('name')->values()->all(), which merges direct + role-inherited and encodes as [] when empty. Tests: tests/Feature/Api/UserApiTest.php (5 tests) — seeded employee gets the nine EMPLOYEE_PERMISSIONS, empty array for a roleless user, exact key set with no sensitive columns, direct permission merged in, 401 unauthenticated. Full suite 614 passed / 4 pre-existing skips; pint and larastan clean.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
GET /api/user now returns App\Http\Resources\UserResource instead of the raw Eloquent model, exposing only id, name, first_name, last_name, rut, email, avatar and a flat permissions array built from getAllPermissions() (direct + role-inherited, [] when empty). Route stays inside the auth:sanctum group and un-prefixed. Verified by tests/Feature/Api/UserApiTest.php (5 tests, 37 assertions): seeded employee returns the nine EMPLOYEE_PERMISSIONS, a roleless user returns a literal [] in the raw JSON, the key set is exactly the eight agreed fields with no password/two_factor/remember_token/internal columns, a directly-granted permission merges with role ones, and an unauthenticated request gets 401. Full suite 614 passed / 4 pre-existing skips; pint --dirty clean; larastan clean on the changed files; no TypeScript touched so types:check is not applicable.
<!-- SECTION:FINAL_SUMMARY:END -->
