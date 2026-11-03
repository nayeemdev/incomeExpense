<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Ledger;
use App\Reports\Period;
use App\Reports\Reports;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __construct(private Reports $reports) {}

    public function overview(Request $request, Ledger $ledger): JsonResponse
    {
        [$period, $interval, $accounts] = $this->input($request, $ledger);

        return $this->respond($ledger, [
            'accounts' => $this->reports->accountChanges($ledger, $period, $accounts),
            'income_expense' => $this->reports->incomeExpense($ledger, $period, $interval, $accounts),
            'expense_categories' => array_slice($this->reports->breakdown($ledger, $period, $interval, 'category', TransactionType::Withdrawal, $accounts), 0, 10),
            'income_categories' => array_slice($this->reports->breakdown($ledger, $period, $interval, 'category', TransactionType::Deposit, $accounts), 0, 10),
            'budgets' => $this->reports->budgets($ledger, $period),
            'net_worth' => $this->reports->balanceSeries($ledger, $period, $interval),
        ], $period, $interval);
    }

    public function breakdown(Request $request, Ledger $ledger, string $groupBy): JsonResponse
    {
        abort_unless(in_array($groupBy, ['category', 'tag', 'budget', 'counterparty'], true), 404);
        [$period, $interval, $accounts] = $this->input($request, $ledger);
        $type = TransactionType::from($request->validate([
            'type' => ['nullable', Rule::in([TransactionType::Withdrawal->value, TransactionType::Deposit->value])],
        ])['type'] ?? TransactionType::Withdrawal->value);

        return $this->respond($ledger, [
            'rows' => $this->reports->breakdown($ledger, $period, $interval, $groupBy, $type, $accounts),
            'buckets' => array_map(fn (Period $bucket) => ['start' => $bucket->start->toDateString(), 'end' => $bucket->end->toDateString()], $period->buckets($interval)),
        ], $period, $interval);
    }

    public function netWorth(Request $request, Ledger $ledger): JsonResponse
    {
        [$period, $interval, $accounts] = $this->input($request, $ledger);

        return $this->respond($ledger, ['points' => $this->reports->balanceSeries($ledger, $period, $interval, $accounts)], $period, $interval);
    }

    public function accountBalance(Request $request, Ledger $ledger, Account $account): JsonResponse
    {
        abort_unless($account->type->isOwn(), 404);
        [$period, $interval] = $this->input($request, $ledger);

        return $this->respond($ledger, ['points' => $this->reports->balanceSeries($ledger, $period, $interval, [$account->id])], $period, $interval);
    }

    /**
     * @return array{Period, string, list<int>}
     */
    private function input(Request $request, Ledger $ledger): array
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
            'interval' => ['nullable', Rule::in(['day', 'week', 'month', 'year'])],
            'accounts' => ['nullable', 'array'],
            'accounts.*' => ['integer'],
        ]);
        $period = new Period(CarbonImmutable::parse($validated['start']), CarbonImmutable::parse($validated['end']));
        $accounts = isset($validated['accounts'])
            ? $ledger->accounts()->whereKey($validated['accounts'])->pluck('id')->all()
            : [];

        return [$period, $validated['interval'] ?? $period->defaultInterval(), array_values($accounts)];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function respond(Ledger $ledger, array $data, Period $period, string $interval): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'currency' => $ledger->primaryCurrency->code,
                'start' => $period->start->toDateString(),
                'end' => $period->end->toDateString(),
                'interval' => $interval,
                'complete' => $this->reports->isComplete(),
            ],
        ]);
    }
}
