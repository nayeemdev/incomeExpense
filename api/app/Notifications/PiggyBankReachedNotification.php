<?php

namespace App\Notifications;

use App\Models\PiggyBank;

class PiggyBankReachedNotification extends LedgerNotification
{
    public function __construct(public PiggyBank $piggyBank) {}

    public function event(): string
    {
        return 'piggy_bank_reached';
    }

    public function ledgerId(): string
    {
        return $this->piggyBank->ledger_id;
    }

    public function title(): string
    {
        return __('Goal reached: :name', ['name' => $this->piggyBank->name]);
    }

    public function body(): string
    {
        return __('You saved the full amount for :name.', ['name' => $this->piggyBank->name]);
    }

    public function path(): string
    {
        return "/{$this->piggyBank->ledger_id}/piggy-banks/{$this->piggyBank->id}";
    }
}
