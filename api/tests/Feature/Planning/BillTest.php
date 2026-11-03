<?php

use App\Models\Account;
use App\Models\Bill;
use Carbon\CarbonImmutable;

it('lists expected dates and payments for a period', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();

    $billId = $this->postJson("/api/v1/ledgers/{$ledger->id}/bills", [
        'name' => 'Internet', 'currency_id' => $ledger->primary_currency_id, 'amount_min' => '30', 'amount_max' => '35',
        'date' => '2026-01-10', 'repeat_freq' => 'monthly', 'object_group' => 'Home',
    ])->assertCreated()->assertJsonPath('data.object_group', 'Home')->json('data.id');

    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
        'type' => 'withdrawal', 'date' => '2026-03-10', 'description' => 'Internet', 'amount' => '32',
        'source_id' => $checking->id, 'destination_name' => 'ISP', 'bill_id' => $billId,
    ]]])->assertCreated();

    $this->getJson("/api/v1/ledgers/{$ledger->id}/bills?start=2026-03-01&end=2026-04-30")
        ->assertJsonPath('data.0.expected_dates', ['2026-03-10', '2026-04-10'])
        ->assertJsonPath('data.0.payments.0.amount', '32.00');
});

it('respects skip and end date', function () {
    $bill = Bill::factory()->make(['date' => '2026-01-31', 'repeat_freq' => 'monthly', 'skip' => 1, 'end_date' => '2026-06-30']);

    $dates = array_map(fn ($date) => $date->toDateString(), $bill->expectedDates(
        CarbonImmutable::parse('2026-01-01'),
        CarbonImmutable::parse('2026-12-31'),
    ));

    expect($dates)->toBe(['2026-01-31', '2026-03-31', '2026-05-31']);
});

it('validates the amount range', function () {
    $ledger = actingAsMember();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/bills", [
        'name' => 'Rent', 'currency_id' => $ledger->primary_currency_id, 'amount_min' => '50', 'amount_max' => '40',
        'date' => '2026-01-01', 'repeat_freq' => 'monthly',
    ])->assertJsonValidationErrors('amount_max');
});
