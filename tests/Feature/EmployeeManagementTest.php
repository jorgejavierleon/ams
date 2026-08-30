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
use App\Support\Rut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

function employeeMasterSpreadsheetFromXlsxResponse(Response $response): Spreadsheet
{
    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, TestResponse::fromBaseResponse($response)->streamedContent());

    try {
        return (new XlsxReader)->load($path);
    } finally {
        unlink($path);
    }
}

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
        'overtime_rest_day_eligible' => false,
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

test('admin can mark an employee eligible for rest-day overtime compensation (KOL-47)', function () {
    $admin = employeeAdmin();

    $this->actingAs($admin)
        ->post(route('employees.store'), employeePayload($admin, [
            'overtime_rest_day_eligible' => true,
        ]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('users', [
        'email' => 'ana@example.com',
        'overtime_rest_day_eligible' => true,
    ]);
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
        'overtime_rest_day_eligible' => true,
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
            ->where('employee.has_additional_sundays', true)
            ->where('employee.overtime_rest_day_eligible', true));
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
        ->from(route('employees.show', $employee))
        ->patch(route('overtime.pacts.revoke', $pact))
        ->assertRedirect(route('employees.show', $employee));

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
        ->from(route('employees.show', $employee))
        ->put(route('overtime.pacts.update', $pact), [
            'user_id' => $employee->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-09-30',
        ])
        ->assertRedirect(route('employees.show', $employee));

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
        ->from(route('employees.show', $employee))
        ->patch(route('overtime.pacts.activate', $pact))
        ->assertRedirect(route('employees.show', $employee));

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
        ->from(route('employees.show', $employee))
        ->post(route('overtime.pacts.store'), [
            'user_id' => $employee->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-10-31',
        ])
        ->assertRedirect(route('employees.show', $employee));

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

// --- Export (Maestro de Trabajadores, KOL-23) ---

test('non-admin users cannot export employees', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('employees.export', ['format' => 'excel']))
        ->assertForbidden();
});

test('an unsupported export format is rejected', function () {
    $admin = employeeAdmin();

    $this->actingAs($admin)
        ->get(route('employees.export', ['format' => 'pdf']))
        ->assertNotFound();
});

test('the excel export contains the full ficha column set with a formatted rut', function () {
    $admin = employeeAdmin();
    $company = Company::factory()->create(['organization_id' => $admin->organization_id, 'social_reason' => 'Acme SpA']);
    $position = Position::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Analista']);
    $premise = Premise::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'Casa Matriz']);
    $costCenter = CostCenter::factory()->create(['organization_id' => $admin->organization_id, 'name' => 'CC-01']);

    User::factory()->employee()->create([
        'organization_id' => $admin->organization_id,
        'company_id' => $company->id,
        'first_name' => 'Ana',
        'last_name' => 'Pérez',
        'second_last_name' => 'Soto',
        'rut' => validRut(12345678),
        'email' => 'ana@example.com',
        'position_id' => $position->id,
        'premise_id' => $premise->id,
        'cost_center_id' => $costCenter->id,
        'contract_type' => ContractType::Indefinido,
        'contract_start_date' => '2026-01-05',
        'contract_end_date' => null,
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('employees.export', ['format' => 'excel']))
        ->assertOk();

    $spreadsheet = employeeMasterSpreadsheetFromXlsxResponse($response->baseResponse);
    $rows = $spreadsheet->getActiveSheet()->toArray();

    expect($rows[0])->toContain('RUT', 'Empresa', 'Centro de costo', 'Sucursal', 'Cargo', 'Tipo de contrato', 'Activo');

    $header = $rows[0];
    $dataRow = array_combine($header, $rows[1]);

    expect($dataRow['Nombre'])->toBe('Ana')
        ->and($dataRow['Apellido paterno'])->toBe('Pérez')
        ->and($dataRow['Apellido materno'])->toBe('Soto')
        ->and($dataRow['RUT'])->toBe(Rut::format(validRut(12345678)))
        ->and($dataRow['Empresa'])->toBe('Acme SpA')
        ->and($dataRow['Centro de costo'])->toBe('CC-01')
        ->and($dataRow['Sucursal'])->toBe('Casa Matriz')
        ->and($dataRow['Cargo'])->toBe('Analista')
        ->and($dataRow['Tipo de contrato'])->toBe('Indefinido')
        ->and($dataRow['Activo'])->toBe('Sí');
});

test('inactive employees are included and flagged rather than excluded by default', function () {
    $admin = employeeAdmin();
    User::factory()->employee()->create(['organization_id' => $admin->organization_id, 'is_active' => false]);

    $response = $this->actingAs($admin)
        ->get(route('employees.export', ['format' => 'excel']))
        ->assertOk();

    $rows = employeeMasterSpreadsheetFromXlsxResponse($response->baseResponse)->getActiveSheet()->toArray();
    $header = $rows[0];
    $dataRow = array_combine($header, $rows[1]);

    expect($dataRow['Activo'])->toBe('No');
});

test('the export respects the is_active filter', function () {
    $admin = employeeAdmin();
    User::factory()->employee()->create(['organization_id' => $admin->organization_id, 'is_active' => true, 'email' => 'active@example.com']);
    User::factory()->employee()->create(['organization_id' => $admin->organization_id, 'is_active' => false, 'email' => 'inactive@example.com']);

    $response = $this->actingAs($admin)
        ->get(route('employees.export', ['format' => 'excel', 'is_active' => '1']))
        ->assertOk();

    $rows = employeeMasterSpreadsheetFromXlsxResponse($response->baseResponse)->getActiveSheet()->toArray();

    expect($rows)->toHaveCount(2); // header + one active employee
});

test('the export respects the search filter', function () {
    $admin = employeeAdmin();
    User::factory()->employee()->create(['organization_id' => $admin->organization_id, 'email' => 'findme@example.com']);
    User::factory()->employee()->create(['organization_id' => $admin->organization_id, 'email' => 'other@example.com']);

    $response = $this->actingAs($admin)
        ->get(route('employees.export', ['format' => 'excel', 'search' => 'findme']))
        ->assertOk();

    $rows = employeeMasterSpreadsheetFromXlsxResponse($response->baseResponse)->getActiveSheet()->toArray();
    $header = $rows[0];
    $dataRow = array_combine($header, $rows[1]);

    expect($rows)->toHaveCount(2)
        ->and($dataRow['Email'])->toBe('findme@example.com');
});

test('the export only includes employees from the current organization', function () {
    $admin = employeeAdmin();
    User::factory()->employee()->create(['organization_id' => $admin->organization_id]);
    User::factory()->employee()->create(); // foreign organization

    $response = $this->actingAs($admin)
        ->get(route('employees.export', ['format' => 'excel']))
        ->assertOk();

    $rows = employeeMasterSpreadsheetFromXlsxResponse($response->baseResponse)->getActiveSheet()->toArray();

    expect($rows)->toHaveCount(2);
});

test('the csv export delimits with a semicolon for the Chilean locale', function () {
    $admin = employeeAdmin();
    User::factory()->employee()->create([
        'organization_id' => $admin->organization_id,
        'rut' => validRut(12345678),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('employees.export', ['format' => 'csv']))
        ->assertOk();

    $content = TestResponse::fromBaseResponse($response->baseResponse)->streamedContent();
    $lines = explode("\n", trim($content));

    expect($lines[0])->toContain(';')
        ->and($lines[1])->toContain(Rut::format(validRut(12345678)));
});

test('every export is recorded in the payroll export activity log', function () {
    $admin = employeeAdmin();
    $employee = User::factory()->employee()->create(['organization_id' => $admin->organization_id]);

    $this->actingAs($admin)
        ->get(route('employees.export', ['format' => 'excel']))
        ->assertOk();

    $activity = Activity::query()
        ->where('log_name', 'payroll_export')
        ->where('description', 'Exported payroll report')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id)
        ->and($activity->properties['report_type'])->toBe('employee-master')
        ->and($activity->properties['format'])->toBe('excel')
        ->and($activity->properties['employee_ids'])->toContain($employee->id);
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
