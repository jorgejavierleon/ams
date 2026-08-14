<?php

use App\Enums\ContractType;
use App\Enums\OvertimePactStatus;
use App\Mail\AuthProfileUpdated;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Organization;
use App\Models\OvertimePact;
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
        'cost_center_id' => null,
        'premise_id' => null,
        'position_id' => null,
        'supervisor_id' => null,
        'contract_start_date' => null,
        'contract_end_date' => null,
        'contract_type' => null,
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

test('employees can be filtered by cost centre', function () {
    $admin = employeeAdmin();
    $costCenter = CostCenter::factory()->create(['organization_id' => $admin->organization_id]);
    User::factory()->employee()->create(['organization_id' => $admin->organization_id, 'cost_center_id' => $costCenter->id]);
    User::factory()->employee()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->get(route('employees.index', ['costCenters' => [$costCenter->id]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('employees.data', 1));
});

test('the employees list surfaces the cost centre each employee charges to', function () {
    $admin = employeeAdmin();
    $costCenter = CostCenter::factory()->create([
        'organization_id' => $admin->organization_id,
        'name' => 'Operaciones',
    ]);
    User::factory()->employee()->create(['organization_id' => $admin->organization_id, 'cost_center_id' => $costCenter->id]);

    $this->actingAs($admin)
        ->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.cost_center', 'Operaciones'));
});

test('an employee with no cost centre assigned still lists and loads', function () {
    $admin = employeeAdmin();
    $employee = User::factory()->employee()->create([
        'organization_id' => $admin->organization_id,
        'cost_center_id' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.cost_center', null));

    $this->actingAs($admin)
        ->get(route('employees.show', $employee))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('employee.cost_center', null));
});

test('a created employee is assigned the organization single company', function () {
    $admin = employeeAdmin();
    $company = Company::factory()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->post(route('employees.store'), employeePayload($admin))
        ->assertRedirect(route('employees.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'ana@example.com',
        'company_id' => $company->id,
    ]);
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

test('creating an employee rejects an invalid rut', function () {
    $admin = employeeAdmin();

    $this->actingAs($admin)
        ->post(route('employees.store'), employeePayload($admin, [
            'rut' => '12.345.678-9',
        ]))
        ->assertSessionHasErrors('rut');
});

test('creating an employee stores the rut without dots regardless of input formatting', function () {
    $admin = employeeAdmin();

    $this->actingAs($admin)
        ->post(route('employees.store'), employeePayload($admin, [
            'rut' => '12.345.678-5',
        ]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', [
        'email' => 'ana@example.com',
        'rut' => '12345678-5',
    ]);
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

// --- Contract type ---

test('an employee can be created with a contract type', function () {
    $admin = employeeAdmin();

    $this->actingAs($admin)
        ->post(route('employees.store'), employeePayload($admin, [
            'contract_type' => ContractType::PlazoFijo->value,
        ]))
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'ana@example.com')->first()->contract_type)
        ->toBe(ContractType::PlazoFijo);
});

test('an employee created without a contract type keeps it empty', function () {
    $admin = employeeAdmin();

    $this->actingAs($admin)
        ->post(route('employees.store'), employeePayload($admin))
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'ana@example.com')->first()->contract_type)->toBeNull();
});

test('the contract type can be updated on an existing employee', function () {
    $admin = employeeAdmin();
    $employee = User::factory()->employee()->contractType(ContractType::PlazoFijo)->create([
        'organization_id' => $admin->organization_id,
    ]);

    $this->actingAs($admin)
        ->patch(route('employees.update', $employee), employeePayload($admin, [
            'contract_type' => ContractType::Indefinido->value,
            'password' => '',
        ]))
        ->assertSessionHasNoErrors();

    expect($employee->fresh()->contract_type)->toBe(ContractType::Indefinido);
});

test('an unknown contract type is rejected', function () {
    $admin = employeeAdmin();

    $this->actingAs($admin)
        ->post(route('employees.store'), employeePayload($admin, [
            'contract_type' => 'part_time',
        ]))
        ->assertSessionHasErrors('contract_type');
});

test('employees can be filtered by contract type', function () {
    $admin = employeeAdmin();
    User::factory()->employee()->contractType(ContractType::Indefinido)->create([
        'organization_id' => $admin->organization_id,
    ]);
    User::factory()->employee()->contractType(ContractType::Honorarios)->create([
        'organization_id' => $admin->organization_id,
    ]);
    User::factory()->employee()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->get(route('employees.index', ['contractTypes' => [ContractType::Honorarios->value]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.contract_type', ContractType::Honorarios->value)
            ->where('filters.contractTypes', [ContractType::Honorarios->value]));
});

test('an unknown contract type filter value is ignored', function () {
    $admin = employeeAdmin();
    User::factory()->employee()->contractType(ContractType::Indefinido)->create([
        'organization_id' => $admin->organization_id,
    ]);

    $this->actingAs($admin)
        ->get(route('employees.index', ['contractTypes' => ['part_time']]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('employees.data', 1));
});

test('the employee detail page shows the translated contract type', function () {
    $admin = employeeAdmin();
    $employee = User::factory()->employee()->contractType(ContractType::PorObraOFaena)->create([
        'organization_id' => $admin->organization_id,
    ]);

    $this->actingAs($admin)
        ->get(route('employees.show', $employee))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('employee.contract_type', 'Por obra o faena'));
});

test('the edit form receives the raw contract type value and its options', function () {
    $admin = employeeAdmin();
    $employee = User::factory()->employee()->contractType(ContractType::Indefinido)->create([
        'organization_id' => $admin->organization_id,
    ]);

    $this->actingAs($admin)
        ->get(route('employees.edit', $employee))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('employee.contract_type', ContractType::Indefinido->value)
            ->has('options.contractTypes', count(ContractType::cases())));
});

test('honorarios is the only contract type that is not an employment contract', function () {
    expect(ContractType::Honorarios->isEmploymentContract())->toBeFalse();
    expect(ContractType::employmentContractCases())->toBe([
        ContractType::Indefinido,
        ContractType::PlazoFijo,
        ContractType::PorObraOFaena,
    ]);
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

// --- Overtime pactos (KOL-63) ---

test('the Turnos tab lists the employee own pactos, newest first, excluding other employees', function () {
    $admin = employeeAdmin();
    $employee = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);
    $otherEmployee = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);

    $older = OvertimePact::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'start_date' => '2026-04-01',
        'end_date' => '2026-06-30',
    ]);
    $newer = OvertimePact::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'start_date' => '2026-07-01',
        'end_date' => '2026-09-30',
    ]);
    OvertimePact::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $otherEmployee->id,
    ]);

    $this->actingAs($admin)
        ->get(route('employees.show', $employee))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('overtimePacts', 2)
            ->where('overtimePacts.0.id', $newer->id)
            ->where('overtimePacts.1.id', $older->id));
});

test('a pacto revoked from the employee page keeps the record and reflects on the list', function () {
    $admin = employeeAdmin();
    $employee = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);
    $pact = OvertimePact::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
    ]);

    $this->actingAs($admin)
        ->patch(route('overtime.pacts.revoke', $pact))
        ->assertRedirect(route('overtime.pacts.index'));

    expect($pact->fresh()->status)->toBe(OvertimePactStatus::Revoked);

    $this->actingAs($admin)
        ->get(route('employees.show', $employee))
        ->assertInertia(fn ($page) => $page
            ->where('overtimePacts.0.id', $pact->id)
            ->where('overtimePacts.0.status.value', 'revoked'));
});

test('a pacto edited from the employee page reflects its new range on the Turnos tab', function () {
    $admin = employeeAdmin();
    $employee = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);
    $pact = OvertimePact::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
    ]);

    $this->actingAs($admin)
        ->put(route('overtime.pacts.update', $pact), [
            'user_id' => $employee->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-09-30',
        ])
        ->assertRedirect(route('overtime.pacts.index'));

    $this->actingAs($admin)
        ->get(route('employees.show', $employee))
        ->assertInertia(fn ($page) => $page
            ->where('overtimePacts.0.id', $pact->id)
            ->where('overtimePacts.0.end_date', '2026-09-30'));
});

test('a revoked pacto reactivated from the employee page reflects as active on the Turnos tab', function () {
    $admin = employeeAdmin();
    $employee = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);
    $pact = OvertimePact::factory()->create([
        'organization_id' => $admin->organization_id,
        'user_id' => $employee->id,
        'status' => OvertimePactStatus::Revoked,
    ]);

    $this->actingAs($admin)
        ->patch(route('overtime.pacts.activate', $pact))
        ->assertRedirect(route('overtime.pacts.index'));

    $this->actingAs($admin)
        ->get(route('employees.show', $employee))
        ->assertInertia(fn ($page) => $page
            ->where('overtimePacts.0.id', $pact->id)
            ->where('overtimePacts.0.status.value', 'active'));
});

test('a pacto created for the employee from their page appears on the Turnos tab', function () {
    $admin = employeeAdmin();
    $employee = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->post(route('overtime.pacts.store'), [
            'user_id' => $employee->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-10-31',
        ])
        ->assertRedirect(route('overtime.pacts.index'));

    $this->actingAs($admin)
        ->get(route('employees.show', $employee))
        ->assertInertia(fn ($page) => $page->has('overtimePacts', 1));
});

test('an admin viewing an employee page is granted manageOvertimePacts', function () {
    $admin = employeeAdmin();
    $employee = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->get(route('employees.show', $employee))
        ->assertInertia(fn ($page) => $page->where('can.manageOvertimePacts', true));
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
