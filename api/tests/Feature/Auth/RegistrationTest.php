<?php

use App\Models\User;

it('makes the first registered user an admin', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'First',
        'email' => 'first@example.com',
        'password' => 'a-long-password-1',
        'password_confirmation' => 'a-long-password-1',
    ])->assertCreated();

    expect(User::sole()->is_admin)->toBeTrue();
});

it('does not make later users admins', function () {
    User::factory()->create();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Second',
        'email' => 'second@example.com',
        'password' => 'a-long-password-1',
        'password_confirmation' => 'a-long-password-1',
    ])->assertCreated();

    expect(User::query()->where('email', 'second@example.com')->sole()->is_admin)->toBeFalse();
});

it('rejects registration when it is closed and a user exists', function () {
    config(['ledgerfly.registration_enabled' => false]);
    User::factory()->create();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Late',
        'email' => 'late@example.com',
        'password' => 'a-long-password-1',
        'password_confirmation' => 'a-long-password-1',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('allows the first user to register when registration is closed', function () {
    config(['ledgerfly.registration_enabled' => false]);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Owner',
        'email' => 'owner@example.com',
        'password' => 'a-long-password-1',
        'password_confirmation' => 'a-long-password-1',
    ])->assertCreated();
});
