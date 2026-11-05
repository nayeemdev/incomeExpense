<?php

namespace App\Http\Controllers;

use App\Enums\InvestmentEventType;
use App\Enums\InvestmentKind;
use App\Enums\RepeatFrequency;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentEvent;
use App\Models\Ledger;
use App\Rules\Amount;
use App\Support\CurrencyConverter;
use App\Support\InvestmentRoadmap;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Views across all investments of a ledger: income over a period, the calendar of what is coming,
 * what-if projections and the allocation the ledger aims for.
 */
class PortfolioController extends Controller
{
    public function __construct(private CurrencyConverter $converter) {}

    /**
     * Income and realised gains per investment over a period, for tax returns and yearly reviews.
     */
    public function income(Request $request, Ledger $ledger): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ]);
        $start = CarbonImmutable::parse($validated['start'] ?? CarbonImmutable::today()->startOfYear()->toDateString());
        $end = CarbonImmutable::parse($validated['end'] ?? CarbonImmutable::today()->endOfYear()->toDateString());
        $primary = Currency::query()->findOrFail($ledger->primary_currency_id);
        $totals = ['income' => '0', 'realized' => '0'];
        $complete = true;
        $rows = [];

        foreach ($this->investments($ledger) as $investment) {
            $events = $investment->events->filter(fn (InvestmentEvent $event) => $event->date->betweenIncluded($start, $end));
            $income = Money::sum('0', ...$events->where('type', InvestmentEventType::Income)->map(fn (InvestmentEvent $event) => $event->amount)->all());
            $realized = Money::sum('0', ...$events->where('type', InvestmentEventType::Sell)
                ->map(fn (InvestmentEvent $event) => Money::of($event->amount)->sub(Money::of((string) $event->cost))->value)->all());

            if (Money::isZero($income) && Money::isZero($realized)) {
                continue;
            }

            $decimals = $investment->currency()->decimal_places;
            $rows[] = [
                'id' => $investment->id,
                'name' => $investment->account->name,
                'kind' => $investment->kind,
                'currency' => $investment->currency()->code,
                'income' => Money::round($income, $decimals),
                'realized' => Money::round($realized, $decimals),
            ];

            foreach (['income' => $income, 'realized' => $realized] as $key => $amount) {
                $converted = $this->converter->convert($amount, $investment->currency()->id, $primary->id, $end, $ledger->id);
                $complete = $complete && $converted !== null;
                $totals[$key] = Money::sum($totals[$key], $converted ?? '0');
            }
        }

        return response()->json(['data' => [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'rows' => $rows,
            'currency' => $primary->code,
            'complete' => $complete,
            'income' => Money::round($totals['income'], $primary->decimal_places),
            'realized' => Money::round($totals['realized'], $primary->decimal_places),
        ]]);
    }

    /**
     * Payouts, installments and maturities coming up, with payouts not yet recorded marked overdue.
     */
    public function calendar(Request $request, Ledger $ledger): JsonResponse
    {
        $request->validate(['months' => ['nullable', 'integer', 'min:1', 'max:36']]);
        $months = $request->integer('months') ?: 12;
        $today = CarbonImmutable::today();
        $until = $today->addMonthsNoOverflow($months);
        $items = [];

        foreach ($this->investments($ledger)->whereNull('closed_at') as $investment) {
            $decimals = $investment->currency()->decimal_places;
            $base = ['investment_id' => $investment->id, 'name' => $investment->account->name, 'kind' => $investment->kind, 'currency' => $investment->currency()->code];
            $payout = $investment->position($today)->payoutAmount();

            foreach ($investment->pendingPayoutDates($until) as $date) {
                $items[] = [...$base, 'type' => 'payout', 'date' => $date->toDateString(), 'amount' => $payout === null ? null : Money::round($payout, $decimals), 'overdue' => $date->lessThan($today)];
            }

            foreach ($investment->contributionDates($until) as $date) {
                $items[] = [...$base, 'type' => 'installment', 'date' => $date->toDateString(), 'amount' => Money::round((string) $investment->contribution_amount, $decimals), 'overdue' => false];
            }

            if ($investment->maturity_date !== null && $investment->maturity_date->betweenIncluded($today, $until)) {
                $value = InvestmentRoadmap::for($investment, $today)['maturity_value'] ?? null;
                $items[] = [...$base, 'type' => 'maturity', 'date' => $investment->maturity_date->toDateString(), 'amount' => $value, 'overdue' => false];
            }
        }

        usort($items, fn (array $a, array $b) => [$a['date'], $a['name']] <=> [$b['date'], $b['name']]);

        return response()->json(['data' => $items]);
    }

    /**
     * Works out the roadmap of an investment that does not exist yet, to compare offers before committing.
     */
    public function projection(Request $request, Ledger $ledger): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', Amount::positive()],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:1000'],
            'payout_frequency' => ['nullable', Rule::enum(RepeatFrequency::class)],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'start_date' => ['required', 'date'],
            'maturity_date' => ['nullable', 'date', 'after:start_date'],
            'contribution_amount' => ['nullable', Amount::positive()],
            'contribution_frequency' => ['nullable', 'required_with:contribution_amount', Rule::enum(RepeatFrequency::class)],
        ]);

        if (empty($validated['payout_frequency']) && empty($validated['maturity_date'])) {
            throw ValidationException::withMessages(['maturity_date' => __('Choose how often it pays out, or when it matures.')]);
        }

        $start = CarbonImmutable::parse($validated['start_date']);
        $frequency = isset($validated['contribution_frequency']) ? RepeatFrequency::from($validated['contribution_frequency']) : null;
        $account = new Account(['currency_id' => $ledger->primary_currency_id]);
        $account->setRelation('currency', Currency::query()->findOrFail($ledger->primary_currency_id));

        $investment = new Investment([
            'kind' => InvestmentKind::Private,
            'interest_rate' => (string) $validated['interest_rate'],
            'payout_frequency' => $validated['payout_frequency'] ?? null,
            'tax_rate' => isset($validated['tax_rate']) ? (string) $validated['tax_rate'] : null,
            'start_date' => $start,
            'maturity_date' => $validated['maturity_date'] ?? null,
            'contribution_amount' => $validated['contribution_amount'] ?? null,
            'contribution_frequency' => $frequency,
            'next_contribution_date' => $frequency?->add($start),
        ]);
        $investment->setRelation('account', $account);
        $investment->setRelation('events', new EloquentCollection([
            new InvestmentEvent(['type' => InvestmentEventType::Buy, 'date' => $start, 'amount' => (string) $validated['amount']]),
        ]));

        $payout = $investment->position($start)->payoutAmount();

        return response()->json(['data' => [
            'payout_amount' => $payout === null ? null : Money::round($payout, $account->currency->decimal_places ?? 2),
            'roadmap' => InvestmentRoadmap::for($investment, $start),
        ]]);
    }

    public function targets(Ledger $ledger): JsonResponse
    {
        return response()->json(['data' => $ledger->investmentTargets()->orderByDesc('percent')->get(['kind', 'percent'])]);
    }

    /**
     * Replaces the target mix. Kinds left out have no target.
     */
    public function updateTargets(Request $request, Ledger $ledger): JsonResponse
    {
        $validated = $request->validate([
            'targets' => ['present', 'array'],
            'targets.*.kind' => ['required', 'distinct', Rule::enum(InvestmentKind::class)],
            'targets.*.percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        /** @var list<array{kind: string, percent: numeric-string|float}> $targets */
        $targets = $validated['targets'];

        if (array_sum(array_map(fn (array $target) => (float) $target['percent'], $targets)) > 100) {
            throw ValidationException::withMessages(['targets' => __('The targets add up to more than 100%.')]);
        }

        DB::transaction(function () use ($ledger, $targets) {
            $ledger->investmentTargets()->delete();

            foreach ($targets as $target) {
                if ((float) $target['percent'] > 0) {
                    $ledger->investmentTargets()->create($target);
                }
            }
        });

        return $this->targets($ledger);
    }

    /**
     * @return Collection<int, Investment>
     */
    private function investments(Ledger $ledger): Collection
    {
        return $ledger->investments()->whereHas('account')->with('account.currency', 'events')->get();
    }
}
