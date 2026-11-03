<?php

namespace App\Notifications;

use App\Models\Account;
use App\Support\Money;

class LowBalanceNotification extends LedgerNotification
{
    public function __construct(public Account $account, public string $balance) {}

    public function event(): string
    {
        return 'low_balance';
    }

    public function ledgerId(): string
    {
        return $this->account->ledger_id;
    }

    public function title(): string
    {
        return __('Low balance on :account', ['account' => $this->account->name]);
    }

    public function body(): string
    {
        return __('The balance is now :balance :currency.', [
            'balance' => Money::round($this->balance, $this->account->currency->decimal_places ?? Money::SCALE),
            'currency' => $this->account->currency?->code,
        ]);
    }

    public function path(): string
    {
        return "/{$this->account->ledger_id}/accounts/{$this->account->id}";
    }
}
