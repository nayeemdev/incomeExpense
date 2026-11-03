<?php

use App\Models\Account;
use App\Models\Budget;
use App\Models\Ledger;
use Illuminate\Support\Facades\Date;

function spend(Ledger $ledger, Account $from, string $amount, string $date, ?int $budgetId): void
{
    test()->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
        'type' => 'withdrawal', 'date' => $date, 'description' => 'Spend', 'amount' => $amount,
        'source_id' => $from->id, 'destination_name' => 'Shop', 'budget_id' => $budgetId,
    ]]])->assertCreated();
}

it('tracks spending against a budget limit', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();
    $budgetId = $this->postJson("/api/v1/ledgers/{$ledger->id}/budgets", ['name' => 'Groceries'])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/ledgers/{$ledger->id}/budgets/{$budgetId}/limits", [
        'currency_id' => $ledger->primary_currency_id, 'start_date' => '2026-03-01', 'end_date' => '2026-03-31', 'amount' => '400',
    ])->assertCreated();

    spend($ledger, $checking, '120', '2026-03-05', $budgetId);
    spend($ledger, $checking, '30', '2026-03-20', $budgetId);
    spend($ledger, $checking, '999', '2026-04-02', $budgetId);

    $this->getJson("/api/v1/ledgers/{$ledger->id}/budgets?start=2026-03-01&end=2026-03-31")
        ->assertJsonPath('data.0.limits.0.amount', '400.00')
        ->assertJsonPath('data.0.limits.0.spent', '150.00')
        ->assertJsonPath('data.0.spent.0.amount', '150.00');
});

it('rejects budgets of another ledger on transactions', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();
    $foreign = Budget::factory()->create();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
        'type' => 'withdrawal', 'date' => '2026-03-05', 'description' => 'x', 'amount' => '1',
        'source_id' => $checking->id, 'destination_name' => 'Shop', 'budget_id' => $foreign->id,
    ]]])->assertJsonValidationErrors('transactions.0.budget_id');
});

it('creates auto budget limits with rollover', function () {
    Date::setTestNow('2026-03-10');
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();

    $budgetId = $this->postJson("/api/v1/ledgers/{$ledger->id}/budgets", [
        'name' => 'Fun', 'auto_budget_type' => 'rollover', 'auto_budget_currency_id' => $ledger->primary_currency_id,
        'auto_budget_amount' => '100', 'auto_budget_period' => 'monthly',
    ])->assertCreated()->assertJsonPath('data.limits.0.amount', '100.00')->json('data.id');

    spend($ledger, $checking, '70', '2026-03-12', $budgetId);

    Date::setTestNow('2026-04-01');
    $this->artisan('budgets:auto')->assertSuccessful();

    $this->getJson("/api/v1/ledgers/{$ledger->id}/budgets/{$budgetId}?start=2026-04-01&end=2026-04-30")
        ->assertJsonPath('data.limits.0.amount', '130.00');
});

it('only subtracts overspending for adjusted auto budgets', function () {
    Date::setTestNow('2026-03-10');
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();
    $budgetId = $this->postJson("/api/v1/ledgers/{$ledger->id}/budgets", [
        'name' => 'Food', 'auto_budget_type' => 'adjusted', 'auto_budget_currency_id' => $ledger->primary_currency_id,
        'auto_budget_amount' => '100', 'auto_budget_period' => 'monthly',
    ])->json('data.id');

    spend($ledger, $checking, '130', '2026-03-12', $budgetId);
    Date::setTestNow('2026-04-01');
    $this->artisan('budgets:auto');

    $this->getJson("/api/v1/ledgers/{$ledger->id}/budgets/{$budgetId}?start=2026-04-01&end=2026-04-30")
        ->assertJsonPath('data.limits.0.amount', '70.00');
});

it('stores the available budget for a period', function () {
    $ledger = actingAsMember();
    $payload = ['currency_id' => $ledger->primary_currency_id, 'start_date' => '2026-03-01', 'end_date' => '2026-03-31'];

    $this->postJson("/api/v1/ledgers/{$ledger->id}/available-budgets", [...$payload, 'amount' => '2000'])->assertCreated();
    $this->postJson("/api/v1/ledgers/{$ledger->id}/available-budgets", [...$payload, 'amount' => '2500'])->assertCreated();

    $this->getJson("/api/v1/ledgers/{$ledger->id}/available-budgets?start=2026-03-01&end=2026-03-31")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.amount', '2500.00');
});
