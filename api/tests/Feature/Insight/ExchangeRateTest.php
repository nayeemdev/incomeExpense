<?php

use App\Models\Account;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Support\CurrencyConverter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

it('converts with the latest rate on or before a date, its inverse, and prefers ledger rates', function () {
    $usd = Currency::findByCode('USD');
    $eur = Currency::findByCode('EUR');
    ExchangeRate::create(['from_currency_id' => $usd->id, 'to_currency_id' => $eur->id, 'date' => '2026-01-01', 'rate' => '0.9']);
    ExchangeRate::create(['from_currency_id' => $usd->id, 'to_currency_id' => $eur->id, 'date' => '2026-02-01', 'rate' => '0.8']);
    $converter = new CurrencyConverter;

    expect($converter->convert('100', $usd->id, $eur->id, CarbonImmutable::parse('2026-01-15')))->toStartWith('90.0')
        ->and($converter->convert('80', $eur->id, $usd->id, CarbonImmutable::parse('2026-03-01')))->toStartWith('100.0')
        ->and($converter->convert('1', $usd->id, $eur->id, CarbonImmutable::parse('2025-12-01')))->toBeNull();

    $ledger = actingAsMember();
    ExchangeRate::create(['ledger_id' => $ledger->id, 'from_currency_id' => $usd->id, 'to_currency_id' => $eur->id, 'date' => '2026-01-01', 'rate' => '0.5']);

    expect((new CurrencyConverter)->convert('10', $usd->id, $eur->id, CarbonImmutable::parse('2026-03-01'), $ledger->id))->toStartWith('5.0');
});

it('fetches rates from the configured provider', function () {
    Http::fake(['*' => Http::response(['result' => 'success', 'rates' => ['USD' => 1, 'EUR' => 0.92, 'BDT' => 121.5, 'XYZ' => 3]])]);
    $ledger = actingAsMember();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/exchange-rates/fetch")->assertJsonPath('data.fetched', 2);
    $this->getJson("/api/v1/ledgers/{$ledger->id}/exchange-rates")->assertJsonCount(2, 'data');
});

it('totals the summary in the primary currency', function () {
    $ledger = actingAsMember();
    $usd = Account::factory()->for($ledger)->currency('USD')->create();
    $eur = Account::factory()->for($ledger)->currency('EUR')->create();
    $this->postJson("/api/v1/ledgers/{$ledger->id}/exchange-rates", [
        'from_currency_id' => Currency::findByCode('EUR')->id, 'to_currency_id' => Currency::findByCode('USD')->id, 'date' => '2026-01-01', 'rate' => '1.1',
    ])->assertCreated();

    foreach ([[$usd, '100'], [$eur, '50']] as [$account, $amount]) {
        $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
            'type' => 'withdrawal', 'date' => '2026-03-10', 'description' => 'x', 'amount' => $amount,
            'source_id' => $account->id, 'destination_name' => 'Shop',
        ]]])->assertCreated();
    }

    $this->getJson("/api/v1/ledgers/{$ledger->id}/summary?start=2026-03-01&end=2026-03-31")
        ->assertJsonPath('data.spent_total', ['currency' => 'USD', 'amount' => '155.00', 'complete' => true]);
});
