<?php

namespace App\Http\Controllers;

use App\Http\Resources\BudgetLimitResource;
use App\Models\Budget;
use App\Models\BudgetLimit;
use App\Models\Ledger;
use App\Rules\Amount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class BudgetLimitController extends Controller
{
    public function store(Request $request, Ledger $ledger, Budget $budget): JsonResponse
    {
        $validated = $request->validate([
            'currency_id' => ['required', Rule::exists('currencies', 'id')],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'amount' => ['required', Amount::positive()],
        ]);

        $limit = $budget->limits()->updateOrCreate(
            [
                'currency_id' => $validated['currency_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
            ],
            ['amount' => $validated['amount'], 'generated' => false],
        );

        return (new BudgetLimitResource($limit->load('currency')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, Ledger $ledger, Budget $budget, BudgetLimit $limit): BudgetLimitResource
    {
        $limit->update([
            ...$request->validate(['amount' => ['required', Amount::positive()]]),
            'generated' => false,
        ]);

        return new BudgetLimitResource($limit->load('currency'));
    }

    public function destroy(Ledger $ledger, Budget $budget, BudgetLimit $limit): Response
    {
        $limit->delete();

        return response()->noContent();
    }
}
