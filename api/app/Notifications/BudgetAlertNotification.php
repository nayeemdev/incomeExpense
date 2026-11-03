<?php

namespace App\Notifications;

use App\Models\BudgetLimit;
use App\Support\Money;

class BudgetAlertNotification extends LedgerNotification
{
    public function __construct(public BudgetLimit $limit, public string $spent, public bool $exceeded) {}

    public function event(): string
    {
        return $this->exceeded ? 'budget_exceeded' : 'budget_warning';
    }

    public function ledgerId(): string
    {
        return $this->limit->budget->ledger_id;
    }

    public function title(): string
    {
        return $this->exceeded
            ? __('Over budget: :budget', ['budget' => $this->limit->budget->name])
            : __(':budget is almost used up', ['budget' => $this->limit->budget->name]);
    }

    public function body(): string
    {
        $decimals = $this->limit->currency->decimal_places;

        return __(':spent of :amount :currency spent.', [
            'spent' => Money::round($this->spent, $decimals),
            'amount' => Money::round($this->limit->amount, $decimals),
            'currency' => $this->limit->currency->code,
        ]);
    }

    public function path(): string
    {
        return "/{$this->ledgerId()}/budgets/{$this->limit->budget_id}";
    }
}
