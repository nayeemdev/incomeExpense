<?php

namespace App\Notifications;

use App\Models\Recurrence;
use App\Models\TransactionGroup;

class RecurringCreatedNotification extends LedgerNotification
{
    public function __construct(public Recurrence $recurrence, public TransactionGroup $group) {}

    public function event(): string
    {
        return 'recurring_created';
    }

    public function ledgerId(): string
    {
        return $this->recurrence->ledger_id;
    }

    public function title(): string
    {
        return __(':title was recorded', ['title' => $this->recurrence->title]);
    }

    public function body(): string
    {
        return __('The recurring transaction for :date was added.', ['date' => $this->group->recurrence_date]);
    }

    public function path(): string
    {
        return "/{$this->recurrence->ledger_id}/transactions/{$this->group->id}";
    }
}
