<?php

namespace App\Actions\Budgets;

use App\Enums\AutoBudgetType;
use App\Models\Budget;
use App\Support\Money;
use Carbon\CarbonImmutable;

class CreateAutoBudgetLimit
{
    public function __construct(private BudgetSpending $spending) {}

    /**
     * Makes sure an automatic budget has a limit for the period that contains $date.
     * Rollover carries over what was left (or overspent) last period, adjusted only
     * subtracts overspending.
     */
    public function handle(Budget $budget, CarbonImmutable $date): void
    {
        $period = $budget->auto_budget_period;
        $currencyId = $budget->getAttribute('auto_budget_currency_id');

        if ($budget->auto_budget_type === null || $period === null || $budget->auto_budget_amount === null || ! is_string($currencyId)) {
            return;
        }

        $start = $period->startOf($date);
        $end = $period->endOf($start);

        if ($budget->limits()->where('currency_id', $currencyId)->whereDate('start_date', $start)->whereDate('end_date', $end)->exists()) {
            return;
        }

        $amount = $budget->auto_budget_amount;

        if ($budget->auto_budget_type !== AutoBudgetType::Reset) {
            $previousStart = $period->startOf($start->subDay());
            $previous = $budget->limits()
                ->where('currency_id', $currencyId)
                ->whereDate('start_date', $previousStart)
                ->first();

            if ($previous !== null) {
                $spent = $this->spending->between([$budget->id], $previous->start_date, $previous->end_date)[$budget->id][$currencyId] ?? '0';
                $left = Money::of($previous->amount)->sub(Money::of($spent))->value;

                if ($budget->auto_budget_type === AutoBudgetType::Rollover || Money::isNegative($left)) {
                    $amount = Money::sum($amount, $left);
                }
            }
        }

        $budget->limits()->create([
            'currency_id' => $currencyId,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'amount' => Money::isNegative($amount) ? '0' : $amount,
            'generated' => true,
        ]);
    }
}
