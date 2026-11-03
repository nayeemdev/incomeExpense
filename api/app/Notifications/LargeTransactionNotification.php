<?php

namespace App\Notifications;

use App\Models\Transaction;
use App\Support\Money;

class LargeTransactionNotification extends LedgerNotification
{
    public function __construct(public Transaction $transaction) {}

    public function event(): string
    {
        return 'large_transaction';
    }

    public function ledgerId(): int
    {
        return $this->transaction->ledger_id;
    }

    public function ntfyTitle(bool $private): string
    {
        return $private ? __('Large transaction recorded') : $this->title();
    }

    public function title(): string
    {
        return __('Large transaction: :amount :currency', [
            'amount' => Money::round($this->transaction->amount, $this->transaction->currency->decimal_places),
            'currency' => $this->transaction->currency->code,
        ]);
    }

    public function body(): string
    {
        return __(':description, from :source to :destination.', [
            'description' => $this->transaction->description,
            'source' => $this->transaction->sourceAccount->name,
            'destination' => $this->transaction->destinationAccount->name,
        ]);
    }

    public function path(): string
    {
        return "/{$this->transaction->ledger_id}/transactions/{$this->transaction->transaction_group_id}";
    }
}
