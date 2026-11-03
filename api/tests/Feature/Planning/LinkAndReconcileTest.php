<?php

use App\Models\Account;

it('links two transactions and describes the link from each side', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();
    $create = fn (string $type, string $description) => $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
        'type' => $type, 'date' => '2026-03-01', 'description' => $description, 'amount' => '20',
        ...($type === 'deposit' ? ['source_name' => 'Shop', 'destination_id' => $checking->id] : ['source_id' => $checking->id, 'destination_name' => 'Shop']),
    ]]])->json('data.id');

    $purchase = $create('withdrawal', 'Shoes');
    $refund = $create('deposit', 'Shoes refund');
    $typeId = collect($this->getJson("/api/v1/ledgers/{$ledger->id}/link-types")->json('data'))->firstWhere('name', 'Refund')['id'];

    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions/{$refund}/links", ['link_type_id' => $typeId, 'group_id' => $purchase])
        ->assertCreated()
        ->assertJsonPath('data.label', 'refunds');

    $this->getJson("/api/v1/ledgers/{$ledger->id}/transactions/{$purchase}/links")
        ->assertJsonPath('data.0.label', 'is refunded by')
        ->assertJsonPath('data.0.group.title', 'Shoes refund');
});

it('reconciles an account to a statement balance', function () {
    $ledger = actingAsMember();
    $id = $this->postJson("/api/v1/ledgers/{$ledger->id}/accounts", [
        'type' => 'asset', 'name' => 'Checking', 'currency_id' => $ledger->primary_currency_id, 'asset_role' => 'default',
        'opening_balance' => '100', 'opening_balance_date' => '2026-01-01',
    ])->json('data.id');

    $this->postJson("/api/v1/ledgers/{$ledger->id}/accounts/{$id}/reconcile", ['date' => '2026-02-01', 'balance' => '95.50'])
        ->assertOk()
        ->assertJsonPath('data.balance', '95.50');

    $this->getJson("/api/v1/ledgers/{$ledger->id}/accounts?type[]=asset")->assertJsonCount(1, 'data');
});
