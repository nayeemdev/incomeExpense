<?php

namespace App\Console\Commands;

use App\Actions\Budgets\CreateAutoBudgetLimit;
use App\Models\Budget;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('budgets:auto')]
#[Description('Create the budget limits of automatic budgets for the current period')]
class CreateAutoBudgetLimits extends Command
{
    public function handle(CreateAutoBudgetLimit $create): void
    {
        $today = CarbonImmutable::today();

        Budget::query()
            ->where('active', true)
            ->whereNotNull('auto_budget_type')
            ->each(fn (Budget $budget) => $create->handle($budget, $today));
    }
}
