<?php

use App\Enums\LedgerRole;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function quickEntryToken(Ledger $ledger, LedgerRole $role = LedgerRole::Owner): User
{
    $user = User::factory()->create();
    $ledger->addMember($user, $role);
    Sanctum::actingAs($user, ['quick-entry']);

    return $user;
}

it('records an expense from just an amount and description', function () {
    $ledger = Ledger::factory()->create();
    $wallet = Account::factory()->for($ledger)->create(['asset_role' => 'cash_wallet', 'order' => 2]);
    $checking = Account::factory()->for($ledger)->create(['order' => 1]);
    quickEntryToken($ledger);

    $this->postJson("/api/v1/ledgers/{$ledger->id}/quick-entry", ['amount' => '3.80', 'description' => 'Coffee'])
        ->assertCreated()
        ->assertJsonPath('data.transactions.0.type', 'withdrawal')
        ->assertJsonPath('data.transactions.0.source.id', $checking->id)
        ->assertJsonPath('data.transactions.0.destination.name', 'Coffee');
});

it('reuses the accounts, category and tags of the last matching transaction', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();
    $card = Account::factory()->for($ledger)->create(['asset_role' => 'credit_card']);

    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
        'type' => 'withdrawal', 'date' => '2026-03-01', 'description' => 'Coffee', 'amount' => '4',
        'source_id' => $card->id, 'destination_name' => 'Corner Cafe', 'category_name' => 'Eating out', 'tags' => ['caffeine'],
    ]]])->assertCreated();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/quick-entry", ['amount' => '4.20', 'description' => 'Coffee'])
        ->assertCreated()
        ->assertJsonPath('data.transactions.0.source.id', $card->id)
        ->assertJsonPath('data.transactions.0.destination.name', 'Corner Cafe')
        ->assertJsonPath('data.transactions.0.category.name', 'Eating out')
        ->assertJsonPath('data.transactions.0.tags.0.name', 'caffeine');
});

it('uses the member default account', function () {
    $ledger = actingAsMember();
    Account::factory()->for($ledger)->create(['order' => 0]);
    $preferred = Account::factory()->for($ledger)->create(['order' => 5]);

    $this->patchJson("/api/v1/ledgers/{$ledger->id}/membership", ['default_account_id' => $preferred->id])
        ->assertJsonPath('data.default_account_id', $preferred->id);

    $this->postJson("/api/v1/ledgers/{$ledger->id}/quick-entry", ['amount' => '1', 'description' => 'Bus', 'type' => 'withdrawal'])
        ->assertJsonPath('data.transactions.0.source.id', $preferred->id);
});

it('uses separate default accounts for expenses and income', function () {
    $ledger = actingAsMember();
    $card = Account::factory()->for($ledger)->create(['order' => 0]);
    $checking = Account::factory()->for($ledger)->create(['order' => 5]);

    $this->patchJson("/api/v1/ledgers/{$ledger->id}/membership", ['default_account_id' => $card->id, 'default_income_account_id' => $checking->id])
        ->assertJsonPath('data.default_account_id', $card->id)
        ->assertJsonPath('data.default_income_account_id', $checking->id);

    $this->postJson("/api/v1/ledgers/{$ledger->id}/quick-entry", ['amount' => '9', 'description' => 'Lunch', 'type' => 'withdrawal'])
        ->assertJsonPath('data.transactions.0.source.id', $card->id);
    $this->postJson("/api/v1/ledgers/{$ledger->id}/quick-entry", ['amount' => '900', 'description' => 'Refund', 'type' => 'deposit', 'counterparty' => 'Shop'])
        ->assertJsonPath('data.transactions.0.destination.id', $checking->id);

    $this->patchJson("/api/v1/ledgers/{$ledger->id}/membership", ['default_income_account_id' => null])
        ->assertJsonPath('data.default_account_id', $card->id)
        ->assertJsonPath('data.default_income_account_id', null);
});

it('records income', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/quick-entry", [
        'amount' => '2500', 'description' => 'Salary', 'type' => 'deposit', 'counterparty' => 'Employer',
    ])->assertCreated()
        ->assertJsonPath('data.transactions.0.source.name', 'Employer')
        ->assertJsonPath('data.transactions.0.destination.id', $checking->id);
});

it('asks for an asset account when none exists', function () {
    $ledger = actingAsMember();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/quick-entry", ['amount' => '1', 'description' => 'x'])
        ->assertJsonValidationErrors('account_id');
});

it('limits quick entry tokens to quick entry endpoints', function () {
    $ledger = Ledger::factory()->create();
    Account::factory()->for($ledger)->create();
    quickEntryToken($ledger);

    $this->getJson('/api/v1/ledgers')->assertOk();
    $this->getJson("/api/v1/ledgers/{$ledger->id}/summary?start=2026-01-01&end=2026-01-31")->assertOk();
    $this->getJson("/api/v1/ledgers/{$ledger->id}/transactions")->assertForbidden();
});

it('does not let viewers quick enter', function () {
    $ledger = Ledger::factory()->create();
    Account::factory()->for($ledger)->create();
    quickEntryToken($ledger, LedgerRole::Viewer);

    $this->postJson("/api/v1/ledgers/{$ledger->id}/quick-entry", ['amount' => '1', 'description' => 'x'])->assertForbidden();
});
