<?php

use App\Models\Holiday;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Http;

uses()->group('saas');

function saasHolidayAdmin(): User
{
    return User::factory()->saasUser()->create();
}

/**
 * @param  array<int, array{date: string, title: string, inalienable: bool}>  $data
 */
function fakeBoostr(array $data): void
{
    Http::fake([
        'api.boostr.cl/*' => Http::response(['status' => 'success', 'data' => $data]),
    ]);
}

// --- Access control ---

test('unauthenticated users are redirected to saas login', function () {
    $this->get(route('saas.holidays.index'))->assertRedirect('/saas/login');
});

test('non-saas users are denied access to the official holidays list', function () {
    $this->actingAs(User::factory()->create(), 'saas')
        ->get(route('saas.holidays.index'))
        ->assertForbidden();
});

test('non-saas users cannot trigger an import', function () {
    Http::fake();

    $this->actingAs(User::factory()->create(), 'saas')
        ->post(route('saas.holidays.sync'), ['year' => 2026])
        ->assertForbidden();

    Http::assertNothingSent();
});

// --- Index ---

test('the saas list shows only official holidays', function () {
    Holiday::factory()->create(['name' => 'Official']);
    Holiday::factory()->forOrganization(Organization::factory()->create())->create(['name' => 'Org owned']);

    $this->actingAs(saasHolidayAdmin(), 'saas')
        ->get(route('saas.holidays.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('saas/holidays/index')
                ->has('holidays.data', 1)
                ->where('holidays.data.0.name', 'Official'),
        );
});

// --- Import ---

test('saas admin can import official holidays from boostr', function () {
    fakeBoostr([
        ['date' => '2026-01-01', 'title' => 'Año Nuevo', 'inalienable' => true],
        ['date' => '2026-05-01', 'title' => 'Día Nacional del Trabajo', 'inalienable' => true],
        ['date' => '2026-04-03', 'title' => 'Viernes Santo', 'inalienable' => false],
    ]);

    $this->actingAs(saasHolidayAdmin(), 'saas')
        ->post(route('saas.holidays.sync'), ['year' => 2026])
        ->assertRedirect(route('saas.holidays.index'));

    expect(Holiday::whereNull('organization_id')->count())->toBe(3);
    $this->assertDatabaseHas('holidays', [
        'organization_id' => null,
        'country' => 'cl',
        'date' => '2026-01-01',
        'name' => 'Año Nuevo',
        'mandatory' => true,
    ]);
    $this->assertDatabaseHas('holidays', [
        'date' => '2026-04-03',
        'mandatory' => false,
    ]);
});

test('importing the same year twice is idempotent and updates in place', function () {
    $admin = saasHolidayAdmin();

    // First request returns the original name, the second a corrected one.
    Http::fake([
        'api.boostr.cl/*' => Http::sequence()
            ->push(['status' => 'success', 'data' => [
                ['date' => '2026-01-01', 'title' => 'Año Nuevo', 'inalienable' => true],
            ]])
            ->push(['status' => 'success', 'data' => [
                ['date' => '2026-01-01', 'title' => 'Año Nuevo (corregido)', 'inalienable' => false],
            ]]),
    ]);

    $this->actingAs($admin, 'saas')->post(route('saas.holidays.sync'), ['year' => 2026]);
    $this->actingAs($admin, 'saas')->post(route('saas.holidays.sync'), ['year' => 2026]);

    expect(Holiday::whereNull('organization_id')->count())->toBe(1);
    $this->assertDatabaseHas('holidays', [
        'date' => '2026-01-01',
        'name' => 'Año Nuevo (corregido)',
        'mandatory' => false,
    ]);
});

test('a failed boostr request imports nothing and reports an error', function () {
    Http::fake(['api.boostr.cl/*' => Http::response('', 503)]);

    $this->actingAs(saasHolidayAdmin(), 'saas')
        ->post(route('saas.holidays.sync'), ['year' => 2026])
        ->assertRedirect(route('saas.holidays.index'));

    expect(Holiday::count())->toBe(0);
});

test('the import year is validated', function () {
    Http::fake();

    $this->actingAs(saasHolidayAdmin(), 'saas')
        ->post(route('saas.holidays.sync'), ['year' => 1800])
        ->assertSessionHasErrors('year');

    Http::assertNothingSent();
});

// --- Console command ---

test('the holidays:sync command imports official holidays', function () {
    fakeBoostr([
        ['date' => '2026-09-18', 'title' => 'Independencia Nacional', 'inalienable' => true],
    ]);

    $this->artisan('holidays:sync', ['year' => 2026])
        ->assertSuccessful();

    $this->assertDatabaseHas('holidays', [
        'organization_id' => null,
        'date' => '2026-09-18',
        'name' => 'Independencia Nacional',
    ]);
});
