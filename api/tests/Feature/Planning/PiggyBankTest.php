<?php

use App\Models\Account;
use App\Models\Ledger;

function fundedAccount(Ledger $ledger, string $balance): Account
{
    $id = test()->postJson("/api/v1/ledgers/{$ledger->id}/accounts", [
        'type' => 'asset', 'name' => 'Savings '.uniqid(), 'currency_id' => $ledger->primary_currency_id, 'asset_role' => 'savings',
        'opening_balance' => $balance, 'opening_balance_date' => '2026-01-01',
    ])->json('data.id');

    return Account::findOrFail($id);
}

it('saves towards a target', function () {
    $ledger = actingAsMember();
    $savings = fundedAccount($ledger, '1000');

    $id = $this->postJson("/api/v1/ledgers/{$ledger->id}/piggy-banks", [
        'name' => 'Holiday', 'currency_id' => $ledger->primary_currency_id, 'target_amount' => '500', 'account_ids' => [$savings->id],
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/ledgers/{$ledger->id}/piggy-banks/{$id}/move", ['account_id' => $savings->id, 'amount' => '200'])
        ->assertOk()
        ->assertJsonPath('data.saved_amount', '200.00')
        ->assertJsonPath('data.left_to_save', '300.00');

    $this->postJson("/api/v1/ledgers/{$ledger->id}/piggy-banks/{$id}/move", ['account_id' => $savings->id, 'amount' => '-50'])
        ->assertJsonPath('data.saved_amount', '150.00')
        ->assertJsonCount(2, 'data.events');
});

it('prevents saving more than the target, the account has, or taking out more than saved', function () {
    $ledger = actingAsMember();
    $savings = fundedAccount($ledger, '100');
    $id = $this->postJson("/api/v1/ledgers/{$ledger->id}/piggy-banks", [
        'name' => 'Phone', 'currency_id' => $ledger->primary_currency_id, 'target_amount' => '80', 'account_ids' => [$savings->id],
    ])->json('data.id');
    $otherId = $this->postJson("/api/v1/ledgers/{$ledger->id}/piggy-banks", [
        'name' => 'Bike', 'currency_id' => $ledger->primary_currency_id, 'account_ids' => [$savings->id],
    ])->json('data.id');

    $move = fn (int $piggy, string $amount) => $this->postJson("/api/v1/ledgers/{$ledger->id}/piggy-banks/{$piggy}/move", ['account_id' => $savings->id, 'amount' => $amount]);

    $move($id, '90')->assertJsonValidationErrors('amount');
    $move($id, '-1')->assertJsonValidationErrors('amount');
    $move($id, '80')->assertOk();
    $move($otherId, '30')->assertJsonValidationErrors('amount');
    $move($otherId, '20')->assertOk();
});

it('only links asset accounts of the ledger', function () {
    $ledger = actingAsMember();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/piggy-banks", [
        'name' => 'Car', 'currency_id' => $ledger->primary_currency_id, 'account_ids' => [Account::factory()->create()->id],
    ])->assertJsonValidationErrors('account_ids.0');
});
