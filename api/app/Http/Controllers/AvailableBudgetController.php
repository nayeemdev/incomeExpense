<?php

namespace App\Http\Controllers;

use App\Models\AvailableBudget;
use App\Models\Ledger;
use App\Rules\Amount;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class AvailableBudgetController extends Controller
{
    public function index(Request $request, Ledger $ledger): JsonResponse
    {
        $validated = $request->validate(['start' => ['required', 'date'], 'end' => ['required', 'date', 'after_or_equal:start']]);

        $items = $ledger->availableBudgets()
            ->with('currency')
            ->whereDate('start_date', '<=', $validated['end'])
            ->whereDate('end_date', '>=', $validated['start'])
            ->orderBy('start_date')
            ->get();

        return response()->json(['data' => $items->map(fn (AvailableBudget $item) => $this->present($item))]);
    }

    public function store(Request $request, Ledger $ledger): JsonResponse
    {
        $validated = $request->validate([
            'currency_id' => ['required', Rule::exists('currencies', 'id')],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'amount' => ['required', Amount::positive()],
        ]);

        $item = $ledger->availableBudgets()->updateOrCreate(
            ['currency_id' => $validated['currency_id'], 'start_date' => $validated['start_date'], 'end_date' => $validated['end_date']],
            ['amount' => $validated['amount']],
        );

        return response()->json(['data' => $this->present($item->load('currency'))], Response::HTTP_CREATED);
    }

    public function destroy(Ledger $ledger, AvailableBudget $availableBudget): Response
    {
        $availableBudget->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(AvailableBudget $item): array
    {
        return [
            'id' => $item->id,
            'currency' => ['id' => $item->currency->id, 'code' => $item->currency->code, 'decimal_places' => $item->currency->decimal_places],
            'start_date' => $item->start_date->toDateString(),
            'end_date' => $item->end_date->toDateString(),
            'amount' => Money::round($item->amount, $item->currency->decimal_places),
        ];
    }
}
