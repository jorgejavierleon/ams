<?php

use App\Models\Mark;
use App\Models\User;

uses()->group('dt');

test('validate mark page renders for authenticated dt users', function () {
    $inspector = User::factory()->dtUser()->create();

    $this->actingAs($inspector, 'dt')
        ->get(route('dt.marks.validate'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dt/marks/validate')
            ->where('mark', null),
        );
});

test('guests cannot access the validate mark page', function () {
    $this->get(route('dt.marks.validate'))
        ->assertRedirect(route('dt.login'));
});

test('web-authenticated users cannot access the validate mark page', function () {
    $user = User::factory()->create(['is_dt' => false]);

    $this->actingAs($user, 'web')
        ->get(route('dt.marks.validate'))
        ->assertRedirect(route('dt.login'));
});

test('a valid checksum returns the mark details', function () {
    $employee = User::factory()->create([
        'name' => 'Juan Pérez',
        'rut' => '11.111.111-1',
    ]);
    $mark = Mark::factory()->create(['user_id' => $employee->id]);
    $inspector = User::factory()->dtUser()->create();

    $this->actingAs($inspector, 'dt')
        ->post(route('dt.marks.validate.store'), ['checksum' => $mark->checksum])
        ->assertRedirect(route('dt.marks.validate'))
        ->assertSessionHas('mark', fn (array $flashed) => $flashed['employee_name'] === 'Juan Pérez'
            && $flashed['employee_rut'] === '11111111-1'
            && $flashed['checksum'] === $mark->checksum,
        );
});

test('the validate page renders the flashed mark result', function () {
    $inspector = User::factory()->dtUser()->create();

    $this->actingAs($inspector, 'dt')
        ->withSession(['mark' => ['employee_name' => 'Juan Pérez', 'checksum' => 'abc123']])
        ->get(route('dt.marks.validate'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dt/marks/validate')
            ->where('mark.employee_name', 'Juan Pérez'),
        );
});

test('an unknown checksum returns a not-found error', function () {
    $inspector = User::factory()->dtUser()->create();

    $this->actingAs($inspector, 'dt')
        ->post(route('dt.marks.validate.store'), ['checksum' => 'does-not-exist'])
        ->assertInvalid(['checksum'])
        ->assertSessionMissing('mark');
});

test('a checksum is required', function () {
    $inspector = User::factory()->dtUser()->create();

    $this->actingAs($inspector, 'dt')
        ->post(route('dt.marks.validate.store'), [])
        ->assertInvalid(['checksum']);
});
