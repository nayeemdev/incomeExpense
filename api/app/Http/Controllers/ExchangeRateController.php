<?php

namespace App\Http\Controllers;

use App\Actions\FetchExchangeRates;
use App\Models\ExchangeRate;
use App\Models\Ledger;
use App\Rules\Amount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Throwable;

class ExchangeRateController extends Controller
{
    public function index(Request $request, Ledger $ledger): JsonResponse
    {
        $filters = $request->validate([
            'from_currency_id' => ['nullable', 'integer'],
            'to_currency_id' => ['nullable', 'integer'],
        ]);

        $rates = ExchangeRate::query()
            ->with('from', 'to')
            ->where(fn ($query) => $query->whereNull('ledger_id')->orWhere('ledger_id', $ledger->id))
            ->when($filters['from_currency_id'] ?? null, fn ($query, int $id) => $query->where('from_currency_id', $id))
            ->when($filters['to_currency_id'] ?? null, fn ($query, int $id) => $query->where('to_currency_id', $id))
            ->when(! isset($filters['from_currency_id']), fn ($query) => $query->where(fn ($query) => $query
                ->where('from_currency_id', $ledger->primary_currency_id)
                ->orWhere('ledger_id', $ledger->id)))
            ->orderByDesc('date')
            ->limit(config()->integer('ledgerfly.pagination.max_per_page'))
            ->get();

        return response()->json(['data' => $rates->map(fn (ExchangeRate $rate) => $this->present($rate))]);
    }

    public function store(Request $request, Ledger $ledger): JsonResponse
    {
        $validated = $request->validate([
            'from_currency_id' => ['required', Rule::exists('currencies', 'id')],
            'to_currency_id' => ['required', 'different:from_currency_id', Rule::exists('currencies', 'id')],
            'date' => ['required', 'date'],
            'rate' => ['required', Amount::positive()],
        ]);

        $rate = ExchangeRate::query()->updateOrCreate(
            ['ledger_id' => $ledger->id, 'from_currency_id' => $validated['from_currency_id'], 'to_currency_id' => $validated['to_currency_id'], 'date' => $validated['date']],
            ['rate' => $validated['rate']],
        );

        return response()->json(['data' => $this->present($rate->load('from', 'to'))], Response::HTTP_CREATED);
    }

    public function destroy(Ledger $ledger, int $exchangeRate): Response
    {
        ExchangeRate::query()->where('ledger_id', $ledger->id)->findOrFail($exchangeRate)->delete();

        return response()->noContent();
    }

    public function fetch(Ledger $ledger, FetchExchangeRates $fetch): JsonResponse
    {
        try {
            $count = $fetch->handle($ledger->primaryCurrency);
        } catch (Throwable $exception) {
            report($exception);
            abort(502, __('The exchange rate provider could not be reached. Try again later or add rates by hand.'));
        }

        return response()->json(['data' => ['fetched' => $count]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ExchangeRate $rate): array
    {
        return [
            'id' => $rate->id,
            'from' => $rate->from->code,
            'to' => $rate->to->code,
            'date' => $rate->date->toDateString(),
            'rate' => rtrim(rtrim($rate->rate, '0'), '.'),
            'manual' => $rate->ledger_id !== null,
        ];
    }
}
