<?php

use App\Models\User;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\Sanctum;

it('issues a full access token for valid credentials', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/tokens', [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'iPhone',
    ])->assertCreated()->assertJsonPath('user.id', $user->id);

    $this->withToken($response->json('token'))->getJson('/api/v1/user')->assertOk();
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/tokens', [
        'email' => $user->email,
        'password' => 'wrong',
        'device_name' => 'iPhone',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('requires a valid two-factor code when 2FA is enabled', function () {
    $provider = app(TwoFactorAuthenticationProvider::class);
    $secret = $provider->generateSecretKey();
    $user = User::factory()->create([
        'two_factor_secret' => encrypt($secret),
        'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one'])),
        'two_factor_confirmed_at' => now(),
    ]);
    $credentials = ['email' => $user->email, 'password' => 'password', 'device_name' => 'iPhone'];

    $this->postJson('/api/v1/auth/tokens', $credentials)->assertJsonValidationErrors('code');
    $this->postJson('/api/v1/auth/tokens', [...$credentials, 'code' => '000000'])->assertJsonValidationErrors('code');
    $this->postJson('/api/v1/auth/tokens', [...$credentials, 'recovery_code' => 'recovery-one'])->assertCreated();
    $this->postJson('/api/v1/auth/tokens', [...$credentials, 'recovery_code' => 'recovery-one'])->assertJsonValidationErrors('code');
});

it('revokes the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('phone')->plainTextToken;

    $this->withToken($token)->deleteJson('/api/v1/auth/tokens/current')->assertNoContent();

    expect($user->tokens()->count())->toBe(0);
});

it('creates and revokes scoped personal access tokens', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $id = $this->postJson('/api/v1/tokens', ['name' => 'Widget', 'abilities' => ['quick-entry']])
        ->assertCreated()
        ->assertJsonPath('data.abilities', ['quick-entry'])
        ->json('data.id');

    $this->getJson('/api/v1/tokens')->assertJsonCount(1, 'data');
    $this->deleteJson("/api/v1/tokens/{$id}")->assertNoContent();
    $this->getJson('/api/v1/tokens')->assertJsonCount(0, 'data');
});

it('keeps quick entry tokens away from account management', function () {
    Sanctum::actingAs(User::factory()->create(), ['quick-entry']);

    $this->getJson('/api/v1/user')->assertForbidden();
    $this->getJson('/api/v1/tokens')->assertForbidden();
});
