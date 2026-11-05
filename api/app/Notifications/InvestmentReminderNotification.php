<?php

namespace App\Notifications;

use App\Models\Investment;
use App\Support\Money;
use Carbon\CarbonImmutable;

class InvestmentReminderNotification extends LedgerNotification
{
    public function __construct(public Investment $investment, public CarbonImmutable $date, public bool $maturing, public ?string $amount = null) {}

    public function event(): string
    {
        return $this->maturing ? 'investment_maturing' : 'investment_payout';
    }

    public function ledgerId(): string
    {
        return $this->investment->ledger_id;
    }

    public function title(): string
    {
        $name = $this->investment->account->name;

        return $this->maturing
            ? __(':name matures :date', ['name' => $name, 'date' => $this->day()])
            : __('Payout from :name is due :date', ['name' => $name, 'date' => $this->day()]);
    }

    public function body(): string
    {
        if ($this->maturing || $this->amount === null) {
            return __('Record what you receive so your balances stay right.');
        }

        $currency = $this->investment->currency();

        return __('Expected :amount :currency on :date. Record it once it arrives.', [
            'amount' => Money::round($this->amount, $currency->decimal_places),
            'currency' => $currency->code,
            'date' => $this->date->toFormattedDayDateString(),
        ]);
    }

    public function path(): string
    {
        return "/{$this->investment->ledger_id}/investments/{$this->investment->id}";
    }

    private function day(): string
    {
        return $this->date->isToday() ? __('today') : $this->date->toFormattedDayDateString();
    }
}
