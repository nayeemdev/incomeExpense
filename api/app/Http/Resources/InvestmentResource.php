<?php

namespace App\Http\Resources;

use App\Models\Investment;
use App\Models\InvestmentEvent;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/** @mixin Investment */
class InvestmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = $this->currency();
        $decimals = $currency->decimal_places;
        $today = CarbonImmutable::today();
        $position = $this->position($today);
        $round = fn (?string $amount) => $amount === null ? null : Money::round($amount, $decimals);
        $payout = $position->payoutAmount();

        return [
            'id' => $this->id,
            'name' => $this->account->name,
            'kind' => $this->kind,
            'tracks_units' => $this->kind->tracksUnits(),
            'symbol' => $this->symbol,
            'account_id' => $this->account_id,
            'currency' => new CurrencyResource($currency),
            'interest_rate' => $this->interest_rate !== null ? Money::round($this->interest_rate, 4) : null,
            'payout_frequency' => $this->payout_frequency,
            'start_date' => $this->start_date?->toDateString(),
            'maturity_date' => $this->maturity_date?->toDateString(),
            'closed_at' => $this->closed_at?->toDateString(),
            'notes' => $this->notes,
            'units' => Money::round($position->units, 6),
            'price' => $position->price !== null ? Money::round($position->price, 6) : null,
            'valued_on' => $position->valuedOn?->toDateString(),
            'cost' => $round($position->cost),
            'value' => $round($position->value()),
            'gain' => $round($position->gain()),
            'gain_percent' => Money::isZero($position->cost) ? null : Money::round(Money::of($position->gain())->mul(100, Money::SCALE)->div(Money::of($position->cost), Money::SCALE)->value, 2),
            'invested' => $round($position->invested),
            'income' => $round($position->income),
            'realized' => $round($position->realized),
            'payout_amount' => $round($payout),
            'upcoming_payouts' => $this->upcomingPayouts($today)->map(fn (CarbonImmutable $date) => [
                'date' => $date->toDateString(),
                'amount' => $round($payout),
                'overdue' => $date->lessThan($today),
            ]),
            'events' => $this->when($request->route('investment') !== null, fn () => $this->events->reverse()->values()->map(fn (InvestmentEvent $event) => [
                'id' => $event->id,
                'type' => $event->type,
                'date' => $event->date->toDateString(),
                'units' => $event->units !== null ? Money::round($event->units, 6) : null,
                'price' => $event->price !== null ? Money::round($event->price, 6) : null,
                'amount' => $round($event->amount),
                'gain' => $event->cost !== null ? $round(Money::of($event->amount)->sub(Money::of($event->cost))->value) : null,
                'transaction_group_id' => $event->transaction_group_id,
            ])),
            'history' => $this->when($request->route('investment') !== null, fn () => $this->history($today, $decimals)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Every expected payout not yet recorded up to today, then the next few.
     *
     * @return Collection<int, CarbonImmutable>
     */
    private function upcomingPayouts(CarbonImmutable $today): Collection
    {
        $pending = collect($this->pendingPayoutDates($today->addYear()));
        $due = $pending->filter(fn (CarbonImmutable $date) => $date->lessThanOrEqualTo($today));

        return $due->merge($pending->diffKeys($due)->take(4))->values();
    }

    /**
     * The value and cost basis after each day with activity, ending today.
     *
     * @return list<array{date: string, value: string, cost: string}>
     */
    private function history(CarbonImmutable $today, int $decimals): array
    {
        $dates = $this->events->toBase()->map(fn (InvestmentEvent $event) => $event->date->toDateString())->push($today->toDateString())->unique()->sort()->values();

        return array_values($dates->map(function (string $date) use ($decimals) {
            $position = $this->position(CarbonImmutable::parse($date));

            return ['date' => $date, 'value' => Money::round($position->value(), $decimals), 'cost' => Money::round($position->cost, $decimals)];
        })->all());
    }
}
