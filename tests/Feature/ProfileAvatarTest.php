<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('avatar can be uploaded', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 200, 200),
        ]);

    $response->assertSessionHasNoErrors();

    expect($user->fresh()->getFirstMedia('avatar'))->not->toBeNull();
});

test('avatar must be an image', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->create('document.pdf', 100),
        ]);

    $response->assertSessionHasErrors('avatar');
});

test('avatar is optional on profile update', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Updated Name',
            'email' => $user->email,
        ]);

    $response->assertSessionHasNoErrors();

    expect($user->fresh()->name)->toBe('Updated Name');
    expect($user->fresh()->getFirstMedia('avatar'))->toBeNull();
});

test('avatar url is exposed via accessor', function () {
    $user = User::factory()->create();
    $user->addMedia(UploadedFile::fake()->image('avatar.jpg'))
        ->toMediaCollection('avatar');

    expect($user->fresh()->avatar)->not->toBeNull()->toBeString();
});
