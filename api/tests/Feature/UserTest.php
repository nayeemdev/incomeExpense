<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns the user with default preferences', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.preferences.theme', config('ledgerfly.default_preferences.theme'))
        ->assertJsonPath('data.two_factor_enabled', false);
});

it('updates preferences', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->patchJson('/api/v1/user/preferences', ['theme' => 'dark', 'timezone' => 'Asia/Dhaka'])
        ->assertOk()
        ->assertJsonPath('data.preferences.theme', 'dark')
        ->assertJsonPath('data.preferences.timezone', 'Asia/Dhaka');
});

it('stores which dashboard widgets are hidden', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->patchJson('/api/v1/user/preferences', ['dashboard_hidden' => ['trend']])->assertJsonPath('data.preferences.dashboard_hidden', ['trend']);
    $this->patchJson('/api/v1/user/preferences', ['dashboard_hidden' => ['nope']])->assertJsonValidationErrors('dashboard_hidden.0');
});

it('rejects unknown preference values', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->patchJson('/api/v1/user/preferences', ['theme' => 'neon'])->assertJsonValidationErrors('theme');
});
