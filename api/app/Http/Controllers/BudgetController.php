<?php

namespace App\Http\Controllers;

use App\Actions\Budgets\BudgetSpending;
use App\Actions\Budgets\CreateAutoBudgetLimit;
use App\Http\Requests\BudgetRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Budget;
use App\Models\BudgetLimit;
use App\Models\Currency;
use App\Models\Ledger;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BudgetController extends Controller
{
    public function __construct(
        private BudgetSpending $spending,
        private CreateAutoBudgetLimit $autoLimit,
    ) {}

    public function index(Request $request, Ledger $ledger): AnonymousResourceCollection
    {
        [$start, $end] = $this->period($request);

        $budgets = $ledger->budgets()
            ->with(['autoBudgetCurrency', 'limits' => fn (HasMany $query) => $this->overlapping($query, $start, $end)->with('currency')])
            ->when($request->has('active'), fn (Builder $query) => $query->where('active', $request->boolean('active')))
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        $this->attachSpending(array_values($budgets->all()), $start, $end);

        return BudgetResource::collection($budgets);
    }

    public function store(BudgetRequest $request, Ledger $ledger): JsonResponse
    {
        $budget = $ledger->budgets()->create($request->validated());
        $this->autoLimit->handle($budget, CarbonImmutable::today());

        return (new BudgetResource($budget->load('autoBudgetCurrency', 'limits.currency')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Ledger $ledger, Budget $budget): BudgetResource
    {
        [$start, $end] = $this->period($request);

        $budget->load(['autoBudgetCurrency', 'limits' => fn (HasMany $query) => $this->overlapping($query, $start, $end)->with('currency')]);
        $this->attachSpending([$budget], $start, $end);

        return new BudgetResource($budget);
    }

    public function update(BudgetRequest $request, Ledger $ledger, Budget $budget): BudgetResource
    {
        $budget->update($request->validated());
        $this->autoLimit->handle($budget, CarbonImmutable::today());

        return new BudgetResource($budget->load('autoBudgetCurrency', 'limits.currency'));
    }

    public function destroy(Ledger $ledger, Budget $budget): Response
    {
        $budget->delete();

        return response()->noContent();
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'start' => ['nullable', 'date', 'required_with:end'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ]);

        $start = isset($validated['start']) ? CarbonImmutable::parse($validated['start']) : CarbonImmutable::today()->startOfMonth();
        $end = isset($validated['end']) ? CarbonImmutable::parse($validated['end']) : $start->endOfMonth();

        return [$start, $end];
    }

    /**
     * @param  HasMany<BudgetLimit, Budget>  $query
     * @return HasMany<BudgetLimit, Budget>
     */
    private function overlapping(HasMany $query, CarbonImmutable $start, CarbonImmutable $end): HasMany
    {
        return $query->whereDate('start_date', '<=', $end)->whereDate('end_date', '>=', $start)->orderBy('start_date');
    }

    /**
     * Adds spending for the requested period to each budget and, for each limit, the
     * spending within that limit's own period.
     *
     * @param  list<Budget>  $budgets
     */
    private function attachSpending(array $budgets, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $ids = array_map(fn (Budget $budget) => $budget->id, $budgets);
        $spent = $this->spending->between($ids, $start, $end);
        $currencies = Currency::query()->whereIn('id', collect($spent)->flatMap(fn (array $byCurrency) => array_keys($byCurrency)))->get()->keyBy('id');

        foreach ($budgets as $budget) {
            $budget->setAttribute('spent', collect($spent[$budget->id] ?? [])
                ->map(function (string $total, string $currencyId) use ($currencies) {
                    $currency = $currencies->get($currencyId) ?? Currency::query()->findOrFail($currencyId);

                    return ['currency' => $currency->code, 'amount' => Money::round($total, $currency->decimal_places)];
                })
                ->values()
                ->all());

            foreach ($budget->limits as $limit) {
                $limitSpent = $this->spending->between([$budget->id], $limit->start_date, $limit->end_date);
                $limit->setAttribute('spent', $limitSpent[$budget->id][$limit->currency_id] ?? '0');
            }
        }
    }
}
