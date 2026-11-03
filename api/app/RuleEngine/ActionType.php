<?php

namespace App\RuleEngine;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Models\Rule;
use App\Models\Transaction;
use App\Notifications\RuleMatchedNotification;
use Illuminate\Support\Facades\Notification;

enum ActionType: string
{
    case SetCategory = 'set_category';
    case ClearCategory = 'clear_category';
    case SetBudget = 'set_budget';
    case ClearBudget = 'clear_budget';
    case LinkToBill = 'link_to_bill';
    case AddTag = 'add_tag';
    case RemoveTag = 'remove_tag';
    case RemoveAllTags = 'remove_all_tags';
    case SetDescription = 'set_description';
    case PrependDescription = 'prepend_description';
    case AppendDescription = 'append_description';
    case SetNotes = 'set_notes';
    case AppendNotes = 'append_notes';
    case ClearNotes = 'clear_notes';
    case SetDestinationAccount = 'set_destination_account';
    case SetSourceAccount = 'set_source_account';
    case DeleteTransaction = 'delete_transaction';
    case Notify = 'notify';

    public function needsValue(): bool
    {
        return ! in_array($this, [
            self::ClearCategory, self::ClearBudget, self::RemoveAllTags,
            self::ClearNotes, self::DeleteTransaction, self::Notify,
        ], true);
    }

    /**
     * Applies the action. Returns false when the transaction no longer exists afterwards.
     */
    public function apply(Transaction $transaction, ?string $value, ?Rule $rule = null): bool
    {
        $value = trim((string) $value);
        $ledger = $transaction->ledger;

        match ($this) {
            self::SetCategory => $transaction->update(['category_id' => $ledger->categories()->firstOrCreate(['name' => $value])->id]),
            self::ClearCategory => $transaction->update(['category_id' => null]),
            self::SetBudget => $transaction->type === TransactionType::Withdrawal
                && ($budget = $ledger->budgets()->where('name', $value)->first())
                && $transaction->update(['budget_id' => $budget->id]),
            self::ClearBudget => $transaction->update(['budget_id' => null]),
            self::LinkToBill => $transaction->type === TransactionType::Withdrawal
                && ($bill = $ledger->bills()->where('name', $value)->first())
                && $transaction->update(['bill_id' => $bill->id]),
            self::AddTag => $transaction->tags()->syncWithoutDetaching([$ledger->tags()->firstOrCreate(['name' => $value])->id]),
            self::RemoveTag => $transaction->tags()->detach($ledger->tags()->where('name', $value)->pluck('id')),
            self::RemoveAllTags => $transaction->tags()->detach(),
            self::SetDescription => $transaction->update(['description' => $value]),
            self::PrependDescription => $transaction->update(['description' => $value.$transaction->description]),
            self::AppendDescription => $transaction->update(['description' => $transaction->description.$value]),
            self::SetNotes => $transaction->update(['notes' => $value]),
            self::AppendNotes => $transaction->update(['notes' => trim($transaction->notes."\n".$value)]),
            self::ClearNotes => $transaction->update(['notes' => null]),
            self::SetDestinationAccount => $this->moveCounterparty($transaction, $value, 'destination'),
            self::SetSourceAccount => $this->moveCounterparty($transaction, $value, 'source'),
            self::DeleteTransaction => $transaction->group->deleteWithTransactions(),
            self::Notify => $rule && Notification::send(
                $ledger->members()->get(),
                new RuleMatchedNotification($rule, $transaction),
            ),
        };

        return $this !== self::DeleteTransaction;
    }

    /**
     * Only the expense or revenue side can be renamed, because own accounts carry
     * balances that must keep matching their postings.
     */
    private function moveCounterparty(Transaction $transaction, string $name, string $side): void
    {
        $type = match (true) {
            $side === 'destination' && $transaction->type === TransactionType::Withdrawal => AccountType::Expense,
            $side === 'source' && $transaction->type === TransactionType::Deposit => AccountType::Revenue,
            default => null,
        };

        if ($type === null || $name === '') {
            return;
        }

        $account = $transaction->ledger->accounts()->firstOrCreate(['type' => $type, 'name' => $name]);
        $previous = $side === 'destination' ? $transaction->destination_account_id : $transaction->source_account_id;

        $transaction->update(["{$side}_account_id" => $account->id]);
        $transaction->postings()->where('account_id', $previous)->update(['account_id' => $account->id]);
    }
}
