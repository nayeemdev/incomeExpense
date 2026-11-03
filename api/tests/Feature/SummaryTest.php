<?php

use App\Models\Account;

it('summarises net worth, spending, income and top categories', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->currency('USD')->create();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/accounts", [
        'type' => 'asset', 'name' => 'Savings', 'currency_id' => $checking->currency_id, 'asset_role' => 'savings',
        'opening_balance' => '1000', 'opening_balance_date' => '2026-01-01',
    ])->assertCreated();

    $entries = [
        ['deposit', '3000', 'Salary', null],
        ['withdrawal', '1200', 'Rent', 'Housing'],
        ['withdrawal', '80', 'Groceries', 'Food'],
        ['withdrawal', '20', 'Snacks', 'Food'],
    ];

    foreach ($entries as [$type, $amount, $description, $category]) {
        $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
            'type' => $type, 'date' => '2026-02-10', 'description' => $description, 'amount' => $amount, 'category_name' => $category,
            ...($type === 'deposit'
                ? ['source_name' => 'Employer', 'destination_id' => $checking->id]
                : ['source_id' => $checking->id, 'destination_name' => $description]),
        ]]])->assertCreated();
    }

    $this->getJson("/api/v1/ledgers/{$ledger->id}/summary?start=2026-02-01&end=2026-02-28")
        ->assertOk()
        ->assertJsonPath('data.net_worth', [['currency' => 'USD', 'amount' => '2700.00']])
        ->assertJsonPath('data.spent', [['currency' => 'USD', 'amount' => '1300.00']])
        ->assertJsonPath('data.earned', [['currency' => 'USD', 'amount' => '3000.00']])
        ->assertJsonPath('data.top_categories.0.name', 'Housing')
        ->assertJsonPath('data.top_categories.1.amount', '100.00')
        ->assertJsonCount(2, 'data.accounts');
});

it('suggests previous transactions by description', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();

    foreach (['2026-01-01' => '3', '2026-02-01' => '4'] as $date => $amount) {
        $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
            'type' => 'withdrawal', 'date' => $date, 'description' => 'Coffee', 'amount' => $amount,
            'source_id' => $checking->id, 'destination_name' => 'Cafe',
        ]]]);
    }

    $this->getJson("/api/v1/ledgers/{$ledger->id}/autocomplete/transactions?search=cof")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.amount', '4.00');
});
