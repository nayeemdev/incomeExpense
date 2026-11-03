<?php

namespace App\Actions\Budgets;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class BudgetSpending
{
    /**
     * Amount spent per budget and currency between two dates, as [budget_id][currency_id] => total.
     *
     * @param  list<int>  $budgetIds
     * @return array<int, array<int, string>>
     */
    public function between(array $budgetIds, CarbonInterface $start, CarbonInterface $end): array
    {
        if ($budgetIds === []) {
            return [];
        }

        $rows = Transaction::query()
            ->whereIn('budget_id', $budgetIds)
            ->where('type', TransactionType::Withdrawal)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('budget_id', 'currency_id')
            ->select('budget_id', 'currency_id', DB::raw('SUM(amount) AS total'))
            ->toBase()
            ->get();

        $spent = [];

        foreach ($rows as $row) {
            /** @var object{budget_id: int, currency_id: int, total: string} $row */
            $spent[$row->budget_id][$row->currency_id] = $row->total;
        }

        return $spent;
    }
}
