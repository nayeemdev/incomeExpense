<?php

namespace App\Actions;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FetchExchangeRates
{
    /**
     * Downloads today's rates from the configured provider with $base as the base currency.
     * The provider must answer with {"rates": {"EUR": 0.92, ...}}.
     *
     * @return int The number of rates stored.
     */
    public function handle(Currency $base): int
    {
        $url = str_replace('{base}', $base->code, config()->string('ledgerfly.exchange_rates.url'));
        $response = Http::timeout(15)->acceptJson()->get($url);
        $rates = $response->json('rates');

        if (! $response->successful() || ! is_array($rates)) {
            throw new RuntimeException("Exchange rates could not be fetched from {$url} (status {$response->status()}).");
        }

        $currencies = Currency::query()->whereIn('code', array_keys($rates))->pluck('id', 'code');
        $today = now()->toDateString();
        $stored = 0;

        foreach ($currencies as $code => $id) {
            if ($id === $base->id || ! is_numeric($rates[$code])) {
                continue;
            }

            ExchangeRate::query()->updateOrCreate(
                ['ledger_id' => null, 'from_currency_id' => $base->id, 'to_currency_id' => $id, 'date' => $today],
                ['rate' => number_format((float) $rates[$code], 12, '.', '')],
            );
            $stored++;
        }

        return $stored;
    }
}
