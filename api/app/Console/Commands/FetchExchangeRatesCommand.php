<?php

namespace App\Console\Commands;

use App\Actions\FetchExchangeRates;
use App\Models\Currency;
use App\Models\Ledger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('exchange-rates:fetch')]
#[Description('Fetch today\'s exchange rates for the primary currency of every ledger')]
class FetchExchangeRatesCommand extends Command
{
    public function handle(FetchExchangeRates $fetch): int
    {
        if (! config('ledgerfly.exchange_rates.enabled')) {
            return self::SUCCESS;
        }

        $failed = false;

        Currency::query()
            ->whereIn('id', Ledger::query()->select('primary_currency_id'))
            ->each(function (Currency $currency) use ($fetch, &$failed) {
                try {
                    $this->info("{$currency->code}: {$fetch->handle($currency)} rates.");
                } catch (Throwable $exception) {
                    $failed = true;
                    report($exception);
                    $this->error($exception->getMessage());
                }
            });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
