<?php

namespace App\Support;

use App\Models\ExchangeRate;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class CurrencyConverter
{
    /** @var array<string, ?string> */
    private array $rates = [];

    /**
     * The most recent rate on or before $date, preferring the ledger's own rates and
     * falling back to the inverse of the opposite pair.
     */
    public function rate(string $fromId, string $toId, CarbonInterface $date, ?string $ledgerId = null): ?string
    {
        if ($fromId === $toId) {
            return '1';
        }

        $key = "{$fromId}:{$toId}:{$date->toDateString()}:{$ledgerId}";

        if (array_key_exists($key, $this->rates)) {
            return $this->rates[$key];
        }

        $direct = $this->find($fromId, $toId, $date, $ledgerId);
        $rate = $direct;

        if ($rate === null) {
            $inverse = $this->find($toId, $fromId, $date, $ledgerId);
            $rate = $inverse !== null && ! Money::isZero($inverse) ? Money::of(1)->div(Money::of($inverse), Money::SCALE)->value : null;
        }

        return $this->rates[$key] = $rate;
    }

    public function convert(string $amount, string $fromId, string $toId, CarbonInterface $date, ?string $ledgerId = null): ?string
    {
        $rate = $this->rate($fromId, $toId, $date, $ledgerId);

        return $rate === null ? null : Money::of($amount)->mul(Money::of($rate), Money::SCALE)->value;
    }

    private function find(string $fromId, string $toId, CarbonInterface $date, ?string $ledgerId): ?string
    {
        $rate = ExchangeRate::query()
            ->where('from_currency_id', $fromId)
            ->where('to_currency_id', $toId)
            ->whereDate('date', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('ledger_id')->when($ledgerId, fn (Builder $query, string $id) => $query->orWhere('ledger_id', $id)))
            ->orderByRaw('ledger_id IS NULL')
            ->orderByDesc('date')
            ->value('rate');

        return is_string($rate) ? $rate : null;
    }
}
