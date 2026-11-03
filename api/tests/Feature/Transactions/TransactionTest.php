<?php

use App\Enums\LedgerRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Ledger;

function balanceOf(Ledger $ledger, Account $account): string
{
    return test()->getJson("/api/v1/ledgers/{$ledger->id}/accounts/{$account->id}")->json('data.balance');
}

it('records a withdrawal and creates the expense account, category and tags by name', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->currency('USD')->create();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", [
        'transactions' => [[
            'type' => 'withdrawal',
            'date' => '2026-03-10',
            'description' => 'Groceries',
            'amount' => '42.30',
            'source_id' => $checking->id,
            'destination_name' => 'Supermarket',
            'category_name' => 'Food',
            'tags' => ['weekly', 'weekly', ' '],
        ]],
    ])->assertCreated()
        ->assertJsonPath('data.transactions.0.amount', '42.30')
        ->assertJsonPath('data.transactions.0.destination.name', 'Supermarket')
        ->assertJsonPath('data.transactions.0.destination.type', 'expense')
        ->assertJsonPath('data.transactions.0.category.name', 'Food')
        ->assertJsonCount(1, 'data.transactions.0.tags');

    expect(balanceOf($ledger, $checking))->toBe('-42.30');
});

it('stores a split transaction as one group', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();
    $food = Category::factory()->for($ledger)->create();

    $split = fn (string $amount, string $description) => [
        'type' => 'withdrawal', 'date' => '2026-03-10', 'description' => $description,
        'amount' => $amount, 'source_id' => $checking->id, 'destination_name' => 'Mall', 'category_id' => $food->id,
    ];

    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", [
        'title' => 'Mall trip',
        'transactions' => [$split('10', 'Lunch'), $split('15.5', 'Snacks')],
    ])->assertCreated()->assertJsonCount(2, 'data.transactions');

    expect(balanceOf($ledger, $checking))->toStartWith('-25.5');
});

it('rejects mixed types within a group', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", [
        'transactions' => [
            ['type' => 'withdrawal', 'date' => '2026-03-10', 'description' => 'a', 'amount' => '1', 'source_id' => $checking->id, 'destination_name' => 'x'],
            ['type' => 'deposit', 'date' => '2026-03-10', 'description' => 'b', 'amount' => '1', 'source_name' => 'y', 'destination_id' => $checking->id],
        ],
    ])->assertJsonValidationErrors('transactions');
});

it('rejects accounts that do not fit the transaction type', function () {
    $ledger = actingAsMember();
    $shop = Account::factory()->for($ledger)->expense()->create();
    $checking = Account::factory()->for($ledger)->create();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", [
        'transactions' => [[
            'type' => 'deposit', 'date' => '2026-03-10', 'description' => 'Wrong', 'amount' => '5',
            'source_id' => $shop->id, 'destination_id' => $checking->id,
        ]],
    ])->assertJsonValidationErrors('transactions.0.source_id');
});

it('requires the foreign amount for a transfer between currencies', function () {
    $ledger = actingAsMember();
    $usd = Account::factory()->for($ledger)->currency('USD')->create();
    $eur = Account::factory()->for($ledger)->currency('EUR')->create();
    $payload = [
        'type' => 'transfer', 'date' => '2026-03-10', 'description' => 'Move', 'amount' => '100',
        'source_id' => $usd->id, 'destination_id' => $eur->id,
    ];

    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [$payload]])
        ->assertJsonValidationErrors('transactions.0.foreign_amount');

    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
        ...$payload, 'foreign_amount' => '92.15', 'foreign_currency_id' => Currency::findByCode('EUR')->id,
    ]]])->assertCreated();

    expect(balanceOf($ledger, $usd))->toBe('-100.00')
        ->and(balanceOf($ledger, $eur))->toBe('92.15');
});

it('lists transactions for an account with a running balance', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->currency('USD')->create();

    foreach ([['2026-01-01', '100', 'deposit'], ['2026-01-02', '30', 'withdrawal'], ['2026-01-03', '20', 'withdrawal']] as [$date, $amount, $type]) {
        $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
            'type' => $type, 'date' => $date, 'description' => $type, 'amount' => $amount,
            ...($type === 'deposit'
                ? ['source_name' => 'Employer', 'destination_id' => $checking->id]
                : ['source_id' => $checking->id, 'destination_name' => 'Shop']),
        ]]])->assertCreated();
    }

    $this->getJson("/api/v1/ledgers/{$ledger->id}/transactions?account_id={$checking->id}")
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.running_balance', '50.00')
        ->assertJsonPath('data.1.running_balance', '70.00')
        ->assertJsonPath('data.2.running_balance', '100.00');
});

it('filters transactions', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();

    foreach (['Coffee' => '2026-02-01', 'Rent' => '2026-03-01'] as $description => $date) {
        $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
            'type' => 'withdrawal', 'date' => $date, 'description' => $description, 'amount' => '5',
            'source_id' => $checking->id, 'destination_name' => $description,
        ]]]);
    }

    $this->getJson("/api/v1/ledgers/{$ledger->id}/transactions?search=cof")->assertJsonCount(1, 'data');
    $this->getJson("/api/v1/ledgers/{$ledger->id}/transactions?start=2026-02-15")->assertJsonCount(1, 'data');
});

it('updates, deletes and restores a transaction group', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();
    $split = [
        'type' => 'withdrawal', 'date' => '2026-03-10', 'description' => 'Taxi', 'amount' => '12',
        'source_id' => $checking->id, 'destination_name' => 'Taxi',
    ];

    $id = $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [$split]])->json('data.id');

    $this->putJson("/api/v1/ledgers/{$ledger->id}/transactions/{$id}", ['transactions' => [[...$split, 'amount' => '15']]])
        ->assertOk()
        ->assertJsonPath('data.transactions.0.amount', fn ($amount) => str_starts_with($amount, '15'));

    $this->deleteJson("/api/v1/ledgers/{$ledger->id}/transactions/{$id}")->assertNoContent();
    expect(balanceOf($ledger, $checking))->toStartWith('0');

    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions/{$id}/restore")->assertOk();
    expect(balanceOf($ledger, $checking))->toStartWith('-15');
});

it('protects opening balance transactions from direct edits', function () {
    $ledger = actingAsMember();
    $id = $this->postJson("/api/v1/ledgers/{$ledger->id}/accounts", [
        'type' => 'asset', 'name' => 'Savings', 'currency_id' => $ledger->primary_currency_id,
        'asset_role' => 'savings', 'opening_balance' => '500', 'opening_balance_date' => '2026-01-01',
    ])->json('data.id');

    $groupId = $this->getJson("/api/v1/ledgers/{$ledger->id}/transactions?account_id={$id}")->json('data.0.group_id');

    $this->deleteJson("/api/v1/ledgers/{$ledger->id}/transactions/{$groupId}")->assertForbidden();
});

it('lets viewers read but not write transactions', function () {
    $ledger = actingAsMember(LedgerRole::Viewer);
    $checking = Account::factory()->for($ledger)->create();

    $this->getJson("/api/v1/ledgers/{$ledger->id}/transactions")->assertOk();
    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
        'type' => 'withdrawal', 'date' => '2026-03-10', 'description' => 'x', 'amount' => '1',
        'source_id' => $checking->id, 'destination_name' => 'x',
    ]]])->assertForbidden();
});

it('deletes the transactions of a deleted account', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();
    $savings = Account::factory()->for($ledger)->create();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
        'type' => 'transfer', 'date' => '2026-03-10', 'description' => 'Save', 'amount' => '50',
        'source_id' => $checking->id, 'destination_id' => $savings->id,
    ]]])->assertCreated();

    $this->deleteJson("/api/v1/ledgers/{$ledger->id}/accounts/{$checking->id}")->assertNoContent();

    expect(balanceOf($ledger, $savings))->toStartWith('0');
});
