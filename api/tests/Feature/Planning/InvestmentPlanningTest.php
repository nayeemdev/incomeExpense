<?php

use App\Enums\LedgerRole;
use App\Models\Account;
use App\Models\Investment;
use App\Models\Ledger;
use App\Models\User;
use Illuminate\Support\Facades\Date;

function planningCash(Ledger $ledger, string $balance = '1000000'): Account
{
    $id = test()->postJson("/api/v1/ledgers/{$ledger->id}/accounts", [
        'type' => 'asset', 'name' => 'Bank '.uniqid(), 'currency_id' => $ledger->primary_currency_id, 'asset_role' => 'default',
        'opening_balance' => $balance, 'opening_balance_date' => '2025-01-01',
    ])->json('data.id');

    return Account::findOrFail($id);
}

beforeEach(fn () => Date::setTestNow('2026-11-05'));

it('takes tax off expected payouts and interest that builds up', function () {
    $ledger = actingAsMember();
    $bank = planningCash($ledger);

    $this->postJson("/api/v1/ledgers/{$ledger->id}/investments", [
        'name' => 'Sanchayapatra', 'kind' => 'savings_scheme', 'currency_id' => $ledger->primary_currency_id,
        'interest_rate' => '12', 'tax_rate' => '10', 'payout_frequency' => 'quarterly', 'start_date' => '2026-10-01',
        'purchase' => ['amount' => '100000', 'date' => '2026-10-01', 'account_id' => $bank->id],
    ])->assertCreated()
        ->assertJsonPath('data.payout_amount', '2700.00')
        ->assertJsonPath('data.tax_rate', '10.0000');
});

it('renews a matured deposit with its interest kept invested', function () {
    $ledger = actingAsMember();
    $bank = planningCash($ledger);

    $id = $this->postJson("/api/v1/ledgers/{$ledger->id}/investments", [
        'name' => 'FDR', 'kind' => 'deposit', 'currency_id' => $ledger->primary_currency_id,
        'interest_rate' => '10', 'start_date' => '2025-11-05', 'maturity_date' => '2026-11-05',
        'purchase' => ['amount' => '100000', 'date' => '2025-11-05', 'account_id' => $bank->id],
    ])->assertJsonPath('data.value', '110000.00')->json('data.id');

    $this->postJson("/api/v1/ledgers/{$ledger->id}/investments/{$id}/renew", ['date' => '2026-11-05', 'maturity_date' => '2027-11-05'])
        ->assertOk()
        ->assertJsonPath('data.cost', '110000.00')
        ->assertJsonPath('data.value', '110000.00')
        ->assertJsonPath('data.income', '10000.00')
        ->assertJsonPath('data.invested', '100000.00')
        ->assertJsonPath('data.start_date', '2026-11-05')
        ->assertJsonPath('data.events.0.reinvested', true)
        ->assertJsonPath('data.roadmap.maturity_value', '121000.00');

    expect((float) Account::query()->withBalance()->findOrFail($this->getJson("/api/v1/ledgers/{$ledger->id}/investments/{$id}")->json('data.account_id'))->balance)->toBe(110000.0);
});

it('records installments on schedule and projects the value at maturity', function () {
    $ledger = actingAsMember();
    $bank = planningCash($ledger);

    $id = $this->postJson("/api/v1/ledgers/{$ledger->id}/investments", [
        'name' => 'DPS', 'kind' => 'savings_scheme', 'currency_id' => $ledger->primary_currency_id,
        'interest_rate' => '9', 'start_date' => '2026-09-05', 'maturity_date' => '2027-09-05',
        'contribution_amount' => '5000', 'contribution_frequency' => 'monthly', 'contribution_account_id' => $bank->id, 'next_contribution_date' => '2026-09-05',
    ])->assertCreated()->json('data.id');

    $this->artisan('investments:installments')->assertSuccessful();

    $this->getJson("/api/v1/ledgers/{$ledger->id}/investments/{$id}")
        ->assertJsonPath('data.invested', '15000.00')
        ->assertJsonPath('data.contribution.next_date', '2026-12-05')
        ->assertJsonPath('data.roadmap.installments_left', 10)
        ->assertJsonPath('data.roadmap.installments_total', '50000.00');

    $this->artisan('investments:installments')->assertSuccessful();
    expect(Investment::findOrFail($id)->events()->count())->toBe(3);
});

it('works out the yearly return and the income of a period', function () {
    $ledger = actingAsMember();
    $bank = planningCash($ledger);

    $id = $this->postJson("/api/v1/ledgers/{$ledger->id}/investments", [
        'name' => 'Fund', 'kind' => 'mutual_fund', 'currency_id' => $ledger->primary_currency_id,
        'purchase' => ['amount' => '1000', 'units' => '100', 'date' => '2025-11-05', 'account_id' => $bank->id],
    ])->json('data.id');
    $events = "/api/v1/ledgers/{$ledger->id}/investments/{$id}/events";
    $this->postJson($events, ['type' => 'valuation', 'date' => '2026-11-01', 'price' => '11']);
    $this->postJson($events, ['type' => 'income', 'date' => '2026-03-01', 'amount' => '50', 'account_id' => $bank->id]);

    $rate = $this->getJson("/api/v1/ledgers/{$ledger->id}/investments/{$id}")->json('data.annual_return');
    expect($rate)->toBeGreaterThan(15.0)->toBeLessThan(16.0);

    $this->getJson("/api/v1/ledgers/{$ledger->id}/investments/income?start=2026-01-01&end=2026-12-31")
        ->assertJsonPath('data.income', '50.00')
        ->assertJsonPath('data.rows.0.name', 'Fund');

    $this->getJson("/api/v1/ledgers/{$ledger->id}/investments/income?start=2025-01-01&end=2025-12-31")
        ->assertJsonCount(0, 'data.rows');
});

it('lists what is coming up and projects an offer before it is made', function () {
    $ledger = actingAsMember();
    $bank = planningCash($ledger);

    $this->postJson("/api/v1/ledgers/{$ledger->id}/investments", [
        'name' => 'Local Co', 'kind' => 'private', 'currency_id' => $ledger->primary_currency_id,
        'interest_rate' => '10', 'payout_frequency' => 'quarterly', 'start_date' => '2026-06-01', 'maturity_date' => '2027-06-01',
        'purchase' => ['amount' => '100000', 'date' => '2026-06-01', 'account_id' => $bank->id],
    ]);

    $this->getJson("/api/v1/ledgers/{$ledger->id}/investments/calendar?months=12")
        ->assertJsonPath('data.0.type', 'payout')
        ->assertJsonPath('data.0.overdue', true)
        ->assertJsonPath('data.3.type', 'payout')
        ->assertJsonPath('data.4.type', 'maturity')
        ->assertJsonCount(5, 'data');

    $this->postJson("/api/v1/ledgers/{$ledger->id}/investments/projection", [
        'amount' => '500000', 'interest_rate' => '10', 'payout_frequency' => 'quarterly', 'start_date' => '2026-11-05',
    ])->assertOk()
        ->assertJsonPath('data.payout_amount', '12500.00')
        ->assertJsonPath('data.roadmap.points.0.status', 'expected')
        ->assertJsonPath('data.roadmap.break_even', '2036-11-05');
});

it('keeps allocation targets that add up to at most 100%', function () {
    $ledger = actingAsMember();

    $this->putJson("/api/v1/ledgers/{$ledger->id}/investment-targets", ['targets' => [['kind' => 'stock', 'percent' => 70], ['kind' => 'gold', 'percent' => 40]]])
        ->assertJsonValidationErrors('targets');

    $this->putJson("/api/v1/ledgers/{$ledger->id}/investment-targets", ['targets' => [['kind' => 'stock', 'percent' => 60], ['kind' => 'gold', 'percent' => 40]]])
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->getJson("/api/v1/ledgers/{$ledger->id}/investments")->assertJsonPath('summary.targets.0.kind', 'stock');
});

it('estimates zakat on cash and investments but not property', function () {
    $ledger = actingAsMember();
    planningCash($ledger, '500000');

    foreach ([['Gold', 'gold', '200000'], ['Flat', 'property', '5000000']] as [$name, $kind, $amount]) {
        $this->postJson("/api/v1/ledgers/{$ledger->id}/investments", [
            'name' => $name, 'kind' => $kind, 'currency_id' => $ledger->primary_currency_id,
            'purchase' => ['amount' => $amount, 'units' => $kind === 'gold' ? '20' : null, 'date' => '2025-01-01'],
        ])->assertCreated();
    }

    $this->getJson("/api/v1/ledgers/{$ledger->id}/zakat?gold_price=10000")
        ->assertJsonPath('data.total', '700000.00')
        ->assertJsonPath('data.nisab', '874800.00')
        ->assertJsonPath('data.due', false);

    $this->getJson("/api/v1/ledgers/{$ledger->id}/zakat?gold_price=5000")
        ->assertJsonPath('data.due', true)
        ->assertJsonPath('data.zakat', '17500.00');
});

it('splits an investment between the members who put money in', function () {
    $ledger = actingAsMember();
    $bank = planningCash($ledger);
    $partner = User::factory()->create();
    $ledger->addMember($partner, LedgerRole::Editor);

    $id = $this->postJson("/api/v1/ledgers/{$ledger->id}/investments", [
        'name' => 'Shop', 'kind' => 'private', 'currency_id' => $ledger->primary_currency_id,
        'purchase' => ['amount' => '300000', 'date' => '2026-01-01', 'account_id' => $bank->id],
    ])->json('data.id');

    $this->postJson("/api/v1/ledgers/{$ledger->id}/investments/{$id}/events", [
        'type' => 'buy', 'date' => '2026-02-01', 'amount' => '100000', 'account_id' => $bank->id, 'user_id' => $partner->id,
    ])->assertOk();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/investments/{$id}/events", [
        'type' => 'buy', 'date' => '2026-02-01', 'amount' => '1', 'account_id' => $bank->id, 'user_id' => User::factory()->create()->id,
    ])->assertJsonValidationErrors('user_id');

    $this->getJson("/api/v1/ledgers/{$ledger->id}/investments/{$id}")
        ->assertJsonPath('data.contributors.0.share', 75)
        ->assertJsonPath('data.contributors.1.name', $partner->name)
        ->assertJsonPath('data.contributors.1.value', '100000.00');
});
