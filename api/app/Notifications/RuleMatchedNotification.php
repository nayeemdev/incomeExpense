<?php

namespace App\Notifications;

use App\Models\Rule;
use App\Models\Transaction;

class RuleMatchedNotification extends LedgerNotification
{
    public function __construct(public Rule $rule, public Transaction $transaction) {}

    public function event(): string
    {
        return 'rule_matched';
    }

    public function ledgerId(): string
    {
        return $this->rule->ledger_id;
    }

    public function title(): string
    {
        return __('Rule ":rule" matched', ['rule' => $this->rule->title]);
    }

    public function body(): string
    {
        return __(':description on :date', [
            'description' => $this->transaction->description,
            'date' => $this->transaction->date->toFormattedDayDateString(),
        ]);
    }

    public function path(): string
    {
        return "/{$this->rule->ledger_id}/transactions/{$this->transaction->transaction_group_id}";
    }
}
