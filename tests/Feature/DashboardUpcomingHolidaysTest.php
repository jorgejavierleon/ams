<?php

use App\Models\Holiday;
use App\Models\Organization;
use App\Models\User;

/**
 * The dashboard's "Upcoming holidays" widget (KOL-119.4): visible to every
 * authenticated user with no permission gate, showing the next few holidays
 * ordered by date, filtered to the app's supported country ('cl' — the app
 * is Chile-only today, mirroring SyncOfficialHolidays), with an explicit
 * empty state.
 */
test('holidays are listed nearest-first and past ones are excluded', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $soon = Holiday::factory()->create(['name' => 'Soon', 'date' => now()->addDays(5)->toDateString()]);
    $later = Holiday::factory()->create(['name' => 'Later', 'date' => now()->addDays(20)->toDateString()]);
    Holiday::factory()->create(['name' => 'Past', 'date' => now()->subDay()->toDateString()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('upcomingHolidays', 2)
            ->where('upcomingHolidays.0.id', $soon->id)
            ->where('upcomingHolidays.1.id', $later->id));
});

test('the list is limited to the next 3 holidays', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    foreach (range(1, 5) as $daysAhead) {
        Holiday::factory()->create(['date' => now()->addDays($daysAhead)->toDateString()]);
    }

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('upcomingHolidays', 3));
});

test('holidays outside the app\'s supported country are excluded', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    Holiday::factory()->create(['country' => 'ar', 'date' => now()->addDays(3)->toDateString()]);
    $clHoliday = Holiday::factory()->create(['country' => 'cl', 'date' => now()->addDays(3)->toDateString()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('upcomingHolidays', 1)
            ->where('upcomingHolidays.0.id', $clHoliday->id));
});

test('an organization sees both official and its own holidays', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    Holiday::factory()->create(['date' => now()->addDays(2)->toDateString()]); // official
    Holiday::factory()->forOrganization($organization)->create(['date' => now()->addDays(4)->toDateString()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->has('upcomingHolidays', 2));
});

test('the widget shows an explicit empty state when there are no upcoming holidays', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    Holiday::factory()->create(['date' => now()->subDay()->toDateString()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('upcomingHolidays', []));
});
