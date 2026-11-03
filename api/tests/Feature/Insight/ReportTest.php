<?php

use App\Models\Account;
use App\Models\Currency;
use App\Models\Ledger;
use App\Reports\Period;
use Carbon\CarbonImmutable;

function seedReportData(Ledger $ledger): Account
{
    $checking = Account::factory()->for($ledger)->currency('USD')->create(['name' => 'Checking']);

    $entries = [
        ['deposit', '2026-01-05', '3000', 'Salary', null, 'Employer'],
        ['withdrawal', '2026-01-10', '100', 'Food', 'Food', 'Market'],
        ['withdrawal', '2026-02-10', '150', 'Food', 'Food', 'Market'],
        ['withdrawal', '2026-02-12', '900', 'Rent', 'Housing', 'Landlord'],
    ];

    foreach ($entries as [$type, $date, $amount, $description, $category, $counterparty]) {
        test()->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
            'type' => $type, 'date' => $date, 'amount' => $amount, 'description' => $description, 'category_name' => $category,
            ...($type === 'deposit' ? ['source_name' => $counterparty, 'destination_id' => $checking->id] : ['source_id' => $checking->id, 'destination_name' => $counterparty]),
        ]]])->assertCreated();
    }

    return $checking;
}

it('splits a period into buckets', function () {
    $buckets = (new Period(CarbonImmutable::parse('2026-01-15'), CarbonImmutable::parse('2026-03-10')))->buckets('month');

    expect(array_map(fn ($bucket) => [$bucket->start->toDateString(), $bucket->end->toDateString()], $buckets))->toBe([
        ['2026-01-15', '2026-01-31'],
        ['2026-02-01', '2026-02-28'],
        ['2026-03-01', '2026-03-10'],
    ]);
});

it('builds the overview report', function () {
    $ledger = actingAsMember();
    $ledger->update(['primary_currency_id' => Currency::findByCode('USD')->id]);
    seedReportData($ledger);

    $this->getJson("/api/v1/ledgers/{$ledger->id}/reports/overview?start=2026-01-01&end=2026-02-28&interval=month")
        ->assertOk()
        ->assertJsonPath('meta.complete', true)
        ->assertJsonPath('data.income_expense', [
            ['start' => '2026-01-01', 'end' => '2026-01-31', 'income' => '3000.00', 'expense' => '100.00'],
            ['start' => '2026-02-01', 'end' => '2026-02-28', 'income' => '0.00', 'expense' => '1050.00'],
        ])
        ->assertJsonPath('data.accounts.0.start', '0.00')
        ->assertJsonPath('data.accounts.0.end', '1850.00')
        ->assertJsonPath('data.expense_categories.0.name', 'Housing')
        ->assertJsonPath('data.expense_categories.1.buckets', ['2026-01-01' => '100.00', '2026-02-01' => '150.00'])
        ->assertJsonPath('data.net_worth.1', ['date' => '2026-02-28', 'balance' => '1850.00']);
});

it('breaks spending down by counterparty and tracks an account balance', function () {
    $ledger = actingAsMember();
    $checking = seedReportData($ledger);

    $this->getJson("/api/v1/ledgers/{$ledger->id}/reports/by/counterparty?start=2026-01-01&end=2026-02-28")
        ->assertJsonPath('data.rows.0.name', 'Landlord')
        ->assertJsonPath('data.rows.1.total', '250.00');

    $this->getJson("/api/v1/ledgers/{$ledger->id}/accounts/{$checking->id}/balance-history?start=2026-01-01&end=2026-02-28&interval=month")
        ->assertJsonPath('data.points', [['date' => '2026-01-31', 'balance' => '2900.00'], ['date' => '2026-02-28', 'balance' => '1850.00']]);
});

it('flags reports with currencies that cannot be converted', function () {
    $ledger = actingAsMember();
    $euros = Account::factory()->for($ledger)->currency('EUR')->create();
    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
        'type' => 'withdrawal', 'date' => '2026-01-10', 'amount' => '5', 'description' => 'x', 'source_id' => $euros->id, 'destination_name' => 'Shop',
    ]]]);

    $this->getJson("/api/v1/ledgers/{$ledger->id}/reports/overview?start=2026-01-01&end=2026-01-31")->assertJsonPath('meta.complete', false);
});
