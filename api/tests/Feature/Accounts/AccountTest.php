<?php

use App\Enums\LedgerRole;
use App\Models\Account;
use App\Models\Currency;

it('creates an asset account with an opening balance', function () {
    $ledger = actingAsMember();
    $usd = Currency::findByCode('USD');

    $id = $this->postJson("/api/v1/ledgers/{$ledger->id}/accounts", [
        'type' => 'asset',
        'name' => 'Checking',
        'currency_id' => $usd->id,
        'asset_role' => 'default',
        'opening_balance' => '1250.50',
        'opening_balance_date' => '2026-01-01',
    ])->assertCreated()
        ->assertJsonPath('data.balance', '1250.50')
        ->assertJsonPath('data.opening_balance', '1250.50')
        ->assertJsonPath('data.opening_balance_date', '2026-01-01')
        ->json('data.id');

    $this->patchJson("/api/v1/ledgers/{$ledger->id}/accounts/{$id}", [
        'opening_balance' => '-20',
        'opening_balance_date' => '2026-02-01',
    ])->assertOk()->assertJsonPath('data.balance', '-20.00');

    $this->patchJson("/api/v1/ledgers/{$ledger->id}/accounts/{$id}", ['opening_balance' => null])
        ->assertOk()
        ->assertJsonPath('data.balance', '0.00')
        ->assertJsonPath('data.opening_balance', null);

    expect(Account::query()->where('type', 'initial_balance')->count())->toBe(0);
});

it('records what is owed on a liability as a negative balance', function () {
    $ledger = actingAsMember();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/accounts", [
        'type' => 'liability',
        'name' => 'Car loan',
        'currency_id' => $ledger->primary_currency_id,
        'liability_type' => 'loan',
        'liability_direction' => 'owed',
        'interest' => '4.5',
        'interest_period' => 'yearly',
        'opening_balance' => '10000',
        'opening_balance_date' => '2026-01-01',
    ])->assertCreated()
        ->assertJsonPath('data.opening_balance', fn ($value) => str_starts_with($value, '10000'))
        ->assertJsonPath('data.balance', fn ($value) => str_starts_with($value, '-10000'));
});

it('validates account fields by type', function () {
    $ledger = actingAsMember();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/accounts", ['type' => 'asset', 'name' => 'No currency'])
        ->assertJsonValidationErrors(['currency_id', 'asset_role']);

    $this->postJson("/api/v1/ledgers/{$ledger->id}/accounts", ['type' => 'expense', 'name' => 'Shop', 'opening_balance' => '5'])
        ->assertJsonValidationErrors('opening_balance');

    $this->postJson("/api/v1/ledgers/{$ledger->id}/accounts", ['type' => 'initial_balance', 'name' => 'Hack'])
        ->assertJsonValidationErrors('type');
});

it('rejects duplicate names within the same type', function () {
    $ledger = actingAsMember();
    Account::factory()->for($ledger)->create(['name' => 'Wallet']);

    $this->postJson("/api/v1/ledgers/{$ledger->id}/accounts", [
        'type' => 'asset', 'name' => 'Wallet', 'currency_id' => $ledger->primary_currency_id, 'asset_role' => 'cash_wallet',
    ])->assertJsonValidationErrors('name');
});

it('lists accounts by type with balances and hides system accounts', function () {
    $ledger = actingAsMember();
    Account::factory()->for($ledger)->count(2)->create();
    Account::factory()->for($ledger)->expense()->create();

    $this->getJson("/api/v1/ledgers/{$ledger->id}/accounts?type[]=asset")
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.balance', fn ($value) => $value !== null);
});

it('does not let viewers change accounts', function () {
    $ledger = actingAsMember(LedgerRole::Viewer);
    $account = Account::factory()->for($ledger)->create();

    $this->getJson("/api/v1/ledgers/{$ledger->id}/accounts/{$account->id}")->assertOk();
    $this->patchJson("/api/v1/ledgers/{$ledger->id}/accounts/{$account->id}", ['name' => 'x'])->assertForbidden();
});

it('does not expose accounts of other ledgers', function () {
    $ledger = actingAsMember();
    $foreign = Account::factory()->create();

    $this->getJson("/api/v1/ledgers/{$ledger->id}/accounts/{$foreign->id}")->assertNotFound();
});
