<?php

use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the landing page renders publicly at the root route', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('landing'));
});

test('submitting the contact form persists a lead', function () {
    $this->post('/leads', [
        'name' => 'Interesado Prueba',
        'company' => 'Empresa Prueba SpA',
        'email' => 'interesado@empresa.cl',
        'message' => '¿Tienen soporte para turnos rotativos?',
    ])->assertRedirect();

    $lead = Lead::query()->where('email', 'interesado@empresa.cl')->first();

    expect($lead)->not->toBeNull()
        ->and($lead->name)->toBe('Interesado Prueba')
        ->and($lead->company)->toBe('Empresa Prueba SpA')
        ->and($lead->message)->toBe('¿Tienen soporte para turnos rotativos?');
});

test('a lead does not require a company or a message', function () {
    $this->post('/leads', [
        'name' => 'Interesado Prueba',
        'email' => 'interesado@empresa.cl',
    ])->assertRedirect();

    $lead = Lead::query()->where('email', 'interesado@empresa.cl')->first();

    expect($lead)->not->toBeNull()
        ->and($lead->company)->toBeNull()
        ->and($lead->message)->toBeNull();
});

test('a lead requires a name', function () {
    $this->post('/leads', ['email' => 'interesado@empresa.cl'])
        ->assertInvalid(['name']);

    expect(Lead::query()->count())->toBe(0);
});

test('a lead requires a valid email', function () {
    $this->post('/leads', ['name' => 'Interesado Prueba', 'email' => 'not-an-email'])
        ->assertInvalid(['email']);

    expect(Lead::query()->count())->toBe(0);
});

test('a filled honeypot silently drops the submission', function () {
    $this->post('/leads', [
        'name' => 'Bot Prueba',
        'email' => 'bot@spam.cl',
        'website' => 'https://spam.example.com',
    ])->assertRedirect();

    expect(Lead::query()->count())->toBe(0);
});
