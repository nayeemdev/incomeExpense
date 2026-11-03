<?php

use App\Models\Account;
use App\Models\User;
use App\Notifications\Channels\NtfyChannel;
use App\Notifications\LowBalanceNotification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('saves ntfy settings, keeps the token secret and sends a test', function () {
    Http::fake(['ntfy.example.com*' => Http::response(['id' => 'x'])]);
    actingAsMember();

    $this->patchJson('/api/v1/notification-settings', ['ntfy' => ['server' => 'https://ntfy.example.com/', 'topic' => 'ledgerfly-k7q2m9', 'token' => 'tk_secret']])
        ->assertOk()
        ->assertJsonPath('data.ntfy', ['server' => 'https://ntfy.example.com', 'topic' => 'ledgerfly-k7q2m9', 'has_token' => true, 'private' => true]);

    $this->patchJson('/api/v1/notification-settings', ['ntfy' => ['server' => 'https://ntfy.example.com', 'topic' => 'ledgerfly-k7q2m9', 'private' => false]])
        ->assertJsonPath('data.ntfy.has_token', true);
    $this->getJson('/api/v1/user')->assertJsonMissingPath('data.preferences.ntfy');
    $this->patchJson('/api/v1/notification-settings', ['ntfy' => ['server' => 'https://ntfy.example.com', 'topic' => 'bad topic!']])
        ->assertJsonValidationErrors('ntfy.topic');

    $this->postJson('/api/v1/notification-settings/ntfy-test')->assertOk();
    Http::assertSent(fn (Request $request) => $request->url() === 'https://ntfy.example.com'
        && $request['topic'] === 'ledgerfly-k7q2m9'
        && $request->hasHeader('Authorization', 'Bearer tk_secret'));

    $this->patchJson('/api/v1/notification-settings', ['ntfy' => null])->assertJsonPath('data.ntfy', null);
    $this->postJson('/api/v1/notification-settings/ntfy-test')->assertUnprocessable();
});

it('hides amounts in private mode', function () {
    Http::fake();
    $ledger = actingAsMember();
    $user = $ledger->members()->sole();
    $this->patchJson('/api/v1/notification-settings', ['ntfy' => ['server' => 'https://ntfy.sh', 'topic' => 'ledgerfly-k7q2m9']]);
    $account = Account::factory()->for($ledger)->create(['name' => 'Wallet']);

    (new NtfyChannel)->send(User::query()->findOrFail($user->id), new LowBalanceNotification($account, '12.00'));

    Http::assertSent(fn (Request $request) => $request['title'] === 'Low balance on Wallet'
        && ! str_contains($request['message'], '12'));
});

it('sends nothing for people without ntfy', function () {
    Http::fake();
    $ledger = actingAsMember();

    (new NtfyChannel)->send($ledger->members()->sole(), new LowBalanceNotification(Account::factory()->for($ledger)->create(), '12.00'));

    Http::assertNothingSent();
});

it('explains when the ntfy server cannot be reached', function () {
    Http::fake(fn () => throw new ConnectionException('down'));
    actingAsMember();
    $this->patchJson('/api/v1/notification-settings', ['ntfy' => ['server' => 'https://ntfy.invalid', 'topic' => 'ledgerfly-k7q2m9']]);

    $this->postJson('/api/v1/notification-settings/ntfy-test')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Could not reach the ntfy server. Check its address.');
});
