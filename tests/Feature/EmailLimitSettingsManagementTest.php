<?php

use App\Models\PlatformSetting;
use App\Models\User;

uses()->group('saas');

function saasBaselineAdmin(): User
{
    return User::factory()->saasUser()->create();
}

test('unauthenticated users are redirected to saas login', function () {
    $this->get(route('saas.email-limit-settings.edit'))->assertRedirect('/saas/login');
});

test('non-saas users are denied access', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'saas')
        ->get(route('saas.email-limit-settings.edit'))
        ->assertForbidden();
});

test('a saas admin can view the baseline, unset by default', function () {
    $this->actingAs(saasBaselineAdmin(), 'saas')
        ->get(route('saas.email-limit-settings.edit'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('saas/email-limit-settings/edit')
                ->where('baseline', null)
        );
});

test('a saas admin can set the platform baseline', function () {
    $this->actingAs(saasBaselineAdmin(), 'saas')
        ->patch(route('saas.email-limit-settings.update'), [
            'expected_emails_per_user_per_month' => 12,
        ])
        ->assertRedirect(route('saas.email-limit-settings.edit'));

    expect(PlatformSetting::current()->expected_emails_per_user_per_month)->toBe(12);
});

test('setting the baseline rejects non-positive values', function () {
    $this->actingAs(saasBaselineAdmin(), 'saas')
        ->patch(route('saas.email-limit-settings.update'), [
            'expected_emails_per_user_per_month' => 0,
        ])
        ->assertSessionHasErrors('expected_emails_per_user_per_month');
});
