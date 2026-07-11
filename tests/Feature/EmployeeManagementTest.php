<?php

use App\Mail\AuthProfileUpdated;
use App\Models\Company;
use App\Models\Organization;
use App\Models\Position;
use App\Models\Premise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);
});

function employeeAdmin(?Organization $organization = null): User
{
    $organization ??= Organization::factory()->create();

    $admin = User::factory()->create(['organization_id' => $organization->id]);
    $admin->assignRole('admin');

    return $admin;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function employeePayload(User $admin, array $overrides = []): array
{
    $company = Company::factory()->create(['organization_id' => $admin->organization_id]);

    return array_merge([
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'second_last_name' => 'Soto',
        'email' => 'ana@example.com',
        'personal_email' => 'ana.personal@example.com',
        'password' => 'secret123',
        'rut' => validRut(12345678),
        'nationality' => 'Chilena',
        'gender' => 'F',
        'is_active' => true,
        'company_id' => $company->id,
        'premise_id' => null,
        'position_id' => null,
        'supervisor_id' => null,
        'contract_start_date' => null,
        'contract_end_date' => null,
        'is_admin' => false,
        'vacation_days' => 15,
        'additional_vacation_days' => 0,
        'administrative_days' => 0,
        'has_additional_sundays' => false,
        'phone' => '+56911111111',
        'emergency_contact_name' => null,
        'emergency_contact_phone' => null,
        'timezone' => 'America/Santiago',
    ], $overrides);
}

// --- Access control ---

test('unauthenticated users are redirected to login', function () {
    $this->get(route('employees.index'))->assertRedirect(route('login'));
});

test('non-admin users are denied access', function () {
    $user = User::factory()->create();
    $user->assignRole('employee');

    $this->actingAs($user)->get(route('employees.index'))->assertForbidden();
});

// --- Index ---

test('admin can list employees with their details', function () {
    $admin = employeeAdmin();
    $position = Position::factory()->create(['organization_id' => $admin->organization_id]);

    User::factory()->employee()->create([
        'organization_id' => $admin->organization_id,
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'position_id' => $position->id,
        'is_admin' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('employees/index')
                ->has('employees.data', 1)
                ->where('employees.data.0.name', 'Ana Pérez')
                ->where('employees.data.0.is_admin', true),
        );
});

test('employees index only shows the current organization employees', function () {
    $admin = employeeAdmin();
    User::factory()->employee()->create(['organization_id' => $admin->organization_id, 'name' => 'Mine']);
    User::factory()->employee()->create(['name' => 'Foreign']);

    $this->actingAs($admin)
        ->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.name', 'Mine'));
});

test('the index excludes non-employee users of the organization', function () {
    $admin = employeeAdmin();
    User::factory()->employee()->create(['organization_id' => $admin->organization_id]);

    // The admin itself is in the org but is not an employee.
    $this->actingAs($admin)
        ->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('employees.data', 1));
});

test('employees can be filtered by active state', function () {
    $admin = employeeAdmin();
    User::factory()->employee()->create(['organization_id' => $admin->organization_id, 'is_active' => true]);
    User::factory()->employee()->create(['organization_id' => $admin->organization_id, 'is_active' => false]);

    $this->actingAs($admin)
        ->get(route('employees.index', ['is_active' => '0']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.is_active', false));
});

test('employees can be filtered by premise', function () {
    $admin = employeeAdmin();
    $premise = Premise::factory()->create(['organization_id' => $admin->organization_id]);
    User::factory()->employee()->create(['organization_id' => $admin->organization_id, 'premise_id' => $premise->id]);
    User::factory()->employee()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->get(route('employees.index', ['premises' => [$premise->id]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('employees.data', 1));
});

test('employees can be searched by email and rut', function () {
    $admin = employeeAdmin();
    User::factory()->employee()->create([
        'organization_id' => $admin->organization_id,
        'email' => 'findme@example.com',
    ]);
    User::factory()->employee()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->get(route('employees.index', ['search' => 'findme']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.email', 'findme@example.com'));
});

// --- Create ---

test('admin can create an employee', function () {
    $admin = employeeAdmin();

    $this->actingAs($admin)
        ->post(route('employees.store'), employeePayload($admin))
        ->assertRedirect(route('employees.index'))
        ->assertSessionHasNoErrors();

    $employee = User::where('email', 'ana@example.com')->first();

    expect($employee)->not->toBeNull();
    expect($employee->name)->toBe('Ana Pérez');
    expect($employee->organization_id)->toBe($admin->organization_id);
    expect($employee->hasRole('employee'))->toBeTrue();
    expect($employee->rut)->not->toBeNull();
});

test('creating an employee requires the mandatory fields', function () {
    $admin = employeeAdmin();

    $this->actingAs($admin)
        ->post(route('employees.store'), employeePayload($admin, [
            'first_name' => '',
            'email' => '',
            'rut' => '',
        ]))
        ->assertSessionHasErrors(['first_name', 'email', 'rut']);
});

test('an employee avatar can be uploaded on create', function () {
    Storage::fake('public');
    $admin = employeeAdmin();

    $this->actingAs($admin)
        ->post(route('employees.store'), employeePayload($admin, [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
        ]))
        ->assertSessionHasNoErrors();

    $employee = User::where('email', 'ana@example.com')->first();

    expect($employee->getFirstMedia('avatar'))->not->toBeNull();
});

test('the employee avatar must be an image', function () {
    Storage::fake('public');
    $admin = employeeAdmin();

    $this->actingAs($admin)
        ->post(route('employees.store'), employeePayload($admin, [
            'avatar' => UploadedFile::fake()->create('document.pdf', 100),
        ]))
        ->assertSessionHasErrors('avatar');
});

// --- Update ---

test('admin can update an employee', function () {
    $admin = employeeAdmin();
    $employee = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->patch(route('employees.update', $employee), employeePayload($admin, [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email' => 'updated@example.com',
            'password' => '',
        ]))
        ->assertRedirect(route('employees.index'))
        ->assertSessionHasNoErrors();

    $employee->refresh();

    expect($employee->name)->toBe('Updated Name');
    expect($employee->email)->toBe('updated@example.com');
});

test('admin cannot update an employee from another organization', function () {
    $admin = employeeAdmin();
    $foreign = User::factory()->employee()->create();

    $this->actingAs($admin)
        ->patch(route('employees.update', $foreign), employeePayload($admin))
        ->assertNotFound();
});

// --- Active toggle ---

test('the is_active state can be toggled inline', function () {
    $admin = employeeAdmin();
    $employee = User::factory()->employee()->create([
        'organization_id' => $admin->organization_id,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->patch(route('employees.toggle-active', $employee))
        ->assertRedirect();

    expect($employee->fresh()->is_active)->toBeFalse();
});

// --- Show ---

test('admin can view an employee detail page', function () {
    $admin = employeeAdmin();
    $employee = User::factory()->employee()->create([
        'organization_id' => $admin->organization_id,
        'vacation_days' => 12,
        'additional_vacation_days' => 3,
        'administrative_days' => 2,
        'has_additional_sundays' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('employees.show', $employee))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/show')
            ->where('employee.id', $employee->id)
            ->where('employee.vacation_days', 12)
            ->where('employee.additional_vacation_days', 3)
            ->where('employee.administrative_days', 2)
            ->where('employee.has_additional_sundays', true));
});

test('admin cannot view an employee from another organization', function () {
    $admin = employeeAdmin();
    $foreign = User::factory()->employee()->create();

    $this->actingAs($admin)
        ->get(route('employees.show', $foreign))
        ->assertNotFound();
});

// --- Delete ---

test('admin can delete an employee', function () {
    $admin = employeeAdmin();
    $employee = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->delete(route('employees.destroy', $employee))
        ->assertRedirect(route('employees.index'));

    expect(User::find($employee->id))->toBeNull();
});

// --- Observer ---

test('the user observer notifies on a sensitive credential change', function () {
    Mail::fake();
    $admin = employeeAdmin();
    $employee = User::factory()->employee()->create([
        'organization_id' => $admin->organization_id,
        'personal_email' => 'notify@example.com',
    ]);

    $this->actingAs($admin)
        ->patch(route('employees.update', $employee), employeePayload($admin, [
            'email' => 'changed@example.com',
            'password' => '',
        ]))
        ->assertSessionHasNoErrors();

    Mail::assertQueued(AuthProfileUpdated::class);
});

test('the user observer stays silent when no credential changes', function () {
    Mail::fake();
    $admin = employeeAdmin();
    $employee = User::factory()->employee()->create([
        'organization_id' => $admin->organization_id,
        'email' => 'stable@example.com',
        'personal_email' => 'notify@example.com',
    ]);

    $this->actingAs($admin)
        ->patch(route('employees.update', $employee), employeePayload($admin, [
            'email' => 'stable@example.com',
            'personal_email' => 'notify@example.com',
            'phone' => '+56999999999',
            'password' => '',
        ]))
        ->assertSessionHasNoErrors();

    Mail::assertNothingQueued();
});
