<?php

use App\Models\User;

test('shared Inertia props expose locale, tag, supported locales and translations', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'es')
            ->where('localeTag', 'es-CL')
            ->where('supportedLocales', ['es', 'en'])
            ->has('translations.ui.nav')
        );
});

test('default locale renders Spanish UI translations', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('translations.ui.nav.dashboard', 'Panel')
            ->where('translations.ui.user_menu.logout', 'Cerrar sesión')
        );
});

test('switching the locale persists it and swaps the shared translations', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('locale.update', 'en'))
        ->assertRedirect();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'en')
            ->where('localeTag', 'en-US')
            ->where('translations.ui.nav.dashboard', 'Dashboard')
            ->where('translations.ui.user_menu.logout', 'Log out')
        );
});

test('switching back to Spanish restores the default catalog', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('locale.update', 'en'));
    $this->actingAs($user)->put(route('locale.update', 'es'));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('locale', 'es'));
});

test('an unsupported locale is rejected', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('locale.update', 'de'))
        ->assertNotFound();
});

test('server validation messages resolve to Spanish by default', function () {
    app()->setLocale('es');

    expect(trans('validation.required', ['attribute' => 'nombre']))
        ->toBe('El campo nombre es obligatorio.');
});

test('server validation messages resolve to English when the locale is switched', function () {
    app()->setLocale('en');

    expect(trans('validation.required', ['attribute' => 'name']))
        ->toBe('The name field is required.');
});
