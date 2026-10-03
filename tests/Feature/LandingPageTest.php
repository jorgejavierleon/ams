<?php

use App\Models\DemoRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the landing page renders publicly at the root route', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('landing'));
});

test('submitting the demo request form persists a lead', function () {
    $this->post('/demo-requests', ['email' => 'interesado@empresa.cl'])
        ->assertRedirect();

    expect(DemoRequest::query()->where('email', 'interesado@empresa.cl')->exists())
        ->toBeTrue();
});

test('a demo request requires a valid email', function () {
    $this->post('/demo-requests', ['email' => 'not-an-email'])
        ->assertInvalid(['email']);

    expect(DemoRequest::query()->count())->toBe(0);
});
