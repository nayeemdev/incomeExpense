<?php

namespace App\Notifications;

use App\Models\Bill;
use App\Support\Money;
use Carbon\CarbonImmutable;

class BillReminderNotification extends LedgerNotification
{
    public function __construct(public Bill $bill, public CarbonImmutable $date, public bool $overdue) {}

    public function event(): string
    {
        return $this->overdue ? 'bill_overdue' : 'bill_due';
    }

    public function ledgerId(): int
    {
        return $this->bill->ledger_id;
    }

    public function title(): string
    {
        return $this->overdue
            ? __(':bill is overdue', ['bill' => $this->bill->name])
            : __(':bill is due :date', ['bill' => $this->bill->name, 'date' => $this->date->isToday() ? __('today') : $this->date->toFormattedDayDateString()]);
    }

    public function body(): string
    {
        return __('Expected :amount :currency on :date.', [
            'amount' => Money::round($this->bill->amount_max, $this->bill->currency->decimal_places),
            'currency' => $this->bill->currency->code,
            'date' => $this->date->toFormattedDayDateString(),
        ]);
    }

    public function path(): string
    {
        return "/{$this->bill->ledger_id}/bills/{$this->bill->id}";
    }
}
