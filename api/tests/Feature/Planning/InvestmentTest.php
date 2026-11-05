<?php

use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

function investmentCash(Ledger $ledger, string $balance = '100000'): Account
{
    $id = test()->postJson("/api/v1/ledgers/{$ledger->id}/accounts", [
        'type' => 'asset', 'name' => 'Bank '.uniqid(), 'currency_id' => $ledger->primary_currency_id, 'asset_role' => 'default',
        'opening_balance' => $balance, 'opening_balance_date' => '2026-01-01',
    ])->json('data.id');

    return Account::findOrFail($id);
}

beforeEach(fn () => Date::setTestNow('2026-11-05'));

it('buys, revalues and sells shares with an average cost basis', function () {
    $ledger = actingAsMember();
    $bank = investmentCash($ledger);

    $id = $this->postJson("/api/v1/ledgers/{$ledger->id}/investments", [
        'name' => 'Acme', 'kind' => 'stock', 'symbol' => 'ACME', 'currency_id' => $ledger->primary_currency_id,
        'purchase' => ['amount' => '1000', 'units' => '10', 'date' => '2026-02-01', 'account_id' => $bank->id],
    ])->assertCreated()->assertJsonPath('data.units', '10.000000')->json('data.id');

    $events = "/api/v1/ledgers/{$ledger->id}/investments/{$id}/events";

    $this->postJson($events, ['type' => 'buy', 'date' => '2026-03-01', 'amount' => '2000', 'units' => '10', 'account_id' => $bank->id])
        ->assertJsonPath('data.cost', '3000.00');

    $this->postJson($events, ['type' => 'valuation', 'date' => '2026-04-01', 'price' => '200'])
        ->assertJsonPath('data.value', '4000.00')
        ->assertJsonPath('data.gain', '1000.00')
        ->assertJsonPath('data.gain_percent', '33.33');

    $this->postJson($events, ['type' => 'sell', 'date' => '2026-05-01', 'amount' => '25', 'units' => '30', 'account_id' => $bank->id])
        ->assertJsonValidationErrors('units');

    $this->postJson($events, ['type' => 'sell', 'date' => '2026-05-01', 'amount' => '2500', 'units' => '10', 'account_id' => $bank->id])
        ->assertJsonPath('data.cost', '1500.00')
        ->assertJsonPath('data.realized', '1000.00')
        ->assertJsonPath('data.value', '2500.00')
        ->assertJsonPath('data.events.0.gain', '1000.00');

    $investmentAccount = Account::query()->withBalance()->findOrFail($this->getJson("/api/v1/ledgers/{$ledger->id}/investments/{$id}")->json('data.account_id'));
    expect((float) $investmentAccount->balance)->toBe(1500.0)
        ->and((float) Account::query()->withBalance()->findOrFail($bank->id)->balance)->toBe(99500.0);

    $this->postJson($events, ['type' => 'sell', 'date' => '2026-06-01', 'amount' => '1200', 'units' => '10', 'account_id' => $bank->id])
        ->assertJsonPath('data.closed_at', '2026-06-01')
        ->assertJsonPath('data.realized', '700.00');

    $this->getJson("/api/v1/ledgers/{$ledger->id}/investments?status=open")->assertJsonCount(0, 'data');
});

it('schedules quarterly payouts from a fixed yearly return and books them as income', function () {
    $ledger = actingAsMember();
    $bank = investmentCash($ledger);

    $response = $this->postJson("/api/v1/ledgers/{$ledger->id}/investments", [
        'name' => 'Local Co', 'kind' => 'private', 'currency_id' => $ledger->primary_currency_id,
        'interest_rate' => '10', 'payout_frequency' => 'quarterly', 'start_date' => '2026-06-01',
        'purchase' => ['amount' => '100000', 'date' => '2026-06-01', 'account_id' => $bank->id],
    ])->assertCreated()
        ->assertJsonPath('data.payout_amount', '2500.00')
        ->assertJsonPath('data.upcoming_payouts.0.date', '2026-09-01')
        ->assertJsonPath('data.upcoming_payouts.0.overdue', true)
        ->assertJsonPath('data.upcoming_payouts.1.date', '2026-12-01');

    $id = $response->json('data.id');

    $this->postJson("/api/v1/ledgers/{$ledger->id}/investments/{$id}/events", ['type' => 'income', 'date' => '2026-09-02', 'amount' => '2500', 'account_id' => $bank->id])
        ->assertJsonPath('data.income', '2500.00')
        ->assertJsonPath('data.upcoming_payouts.0.date', '2026-12-01')
        ->assertJsonPath('data.value', '100000.00');

    expect(Transaction::query()->where('type', 'deposit')->where('amount', '2500')->exists())->toBeTrue();
});

it('accrues interest on a deposit that pays at maturity and closes it on withdrawal', function () {
    $ledger = actingAsMember();
    $bank = investmentCash($ledger);

    $id = $this->postJson("/api/v1/ledgers/{$ledger->id}/investments", [
        'name' => 'FDR', 'kind' => 'deposit', 'currency_id' => $ledger->primary_currency_id,
        'interest_rate' => '7.3', 'start_date' => '2026-01-05', 'maturity_date' => '2027-01-05',
        'purchase' => ['amount' => '50000', 'date' => '2026-01-05', 'account_id' => $bank->id],
    ])->assertJsonPath('data.value', '53040.00')->json('data.id');

    $this->postJson("/api/v1/ledgers/{$ledger->id}/investments/{$id}/events", ['type' => 'sell', 'date' => '2026-11-05', 'amount' => '53040', 'account_id' => $bank->id, 'close' => true])
        ->assertJsonPath('data.closed_at', '2026-11-05')
        ->assertJsonPath('data.cost', '0.00')
        ->assertJsonPath('data.realized', '3040.00');
});

it('records existing holdings as an opening balance and counts market value in net worth', function () {
    $ledger = actingAsMember();

    $id = $this->postJson("/api/v1/ledgers/{$ledger->id}/investments", [
        'name' => 'Gold', 'kind' => 'gold', 'currency_id' => $ledger->primary_currency_id,
        'purchase' => ['amount' => '5000', 'units' => '10', 'date' => '2025-01-01'],
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/ledgers/{$ledger->id}/investments/{$id}/events", ['type' => 'buy', 'date' => '2026-01-01', 'amount' => '500', 'units' => '1'])
        ->assertJsonValidationErrors('account_id');

    $this->postJson("/api/v1/ledgers/{$ledger->id}/investments/{$id}/events", ['type' => 'valuation', 'date' => '2026-10-01', 'price' => '800']);

    $this->getJson("/api/v1/ledgers/{$ledger->id}/summary?start=2026-11-01&end=2026-11-30")
        ->assertJsonPath('data.net_worth_total.amount', '8000.00');

    $this->getJson("/api/v1/ledgers/{$ledger->id}/investments")
        ->assertJsonPath('summary.value', '8000.00')
        ->assertJsonPath('summary.gain', '3000.00')
        ->assertJsonPath('summary.allocation.0.kind', 'gold');
});

it('removes an event with its transactions and the investment with its account', function () {
    $ledger = actingAsMember();
    $bank = investmentCash($ledger);

    $data = $this->postJson("/api/v1/ledgers/{$ledger->id}/investments", [
        'name' => 'Fund', 'kind' => 'mutual_fund', 'currency_id' => $ledger->primary_currency_id,
        'purchase' => ['amount' => '1000', 'units' => '100', 'date' => '2026-02-01', 'account_id' => $bank->id],
    ])->json('data');

    $eventId = $this->getJson("/api/v1/ledgers/{$ledger->id}/investments/{$data['id']}")->json('data.events.0.id');

    $this->deleteJson("/api/v1/ledgers/{$ledger->id}/investments/{$data['id']}/events/{$eventId}")
        ->assertOk()
        ->assertJsonPath('data.cost', '0.00')
        ->assertJsonPath('data.closed_at', null);

    expect(Transaction::query()->where('type', 'transfer')->count())->toBe(0);

    $this->deleteJson("/api/v1/ledgers/{$ledger->id}/investments/{$data['id']}")->assertNoContent();
    expect(Account::find($data['account_id']))->toBeNull();
});

it('rejects accounts from another ledger or currency', function () {
    $ledger = actingAsMember();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/investments", [
        'name' => 'X', 'kind' => 'stock', 'currency_id' => $ledger->primary_currency_id,
        'purchase' => ['amount' => '10', 'units' => '1', 'date' => '2026-01-01', 'account_id' => Account::factory()->create()->id],
    ])->assertJsonValidationErrors('purchase.account_id');
});

it('reminds members of due payouts and upcoming maturities', function () {
    Notification::fake();
    $ledger = actingAsMember();
    $bank = investmentCash($ledger);

    $this->postJson("/api/v1/ledgers/{$ledger->id}/investments", [
        'name' => 'Local Co', 'kind' => 'private', 'currency_id' => $ledger->primary_currency_id,
        'interest_rate' => '12', 'payout_frequency' => 'monthly', 'start_date' => '2026-10-05', 'maturity_date' => '2026-11-07',
        'purchase' => ['amount' => '1000', 'date' => '2026-10-05', 'account_id' => $bank->id],
    ])->assertCreated();

    $this->artisan('notifications:investments')->assertSuccessful();
    $this->artisan('notifications:investments')->assertSuccessful();

    Notification::assertCount(2);
});
