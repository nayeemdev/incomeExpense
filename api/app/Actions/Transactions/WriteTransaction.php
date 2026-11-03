<?php

namespace App\Actions\Transactions;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

class WriteTransaction
{
    /**
     * Writes one split and its two balancing postings. Each posting is stored in the
     * currency of its account, so an account balance is always a plain sum.
     *
     * @param  array{type: TransactionType, date: string, description: string, amount: string, foreign_amount?: ?string, foreign_currency_id?: ?int, category_id?: ?int, notes?: ?string}  $attributes
     * @param  list<int>  $tagIds
     */
    public function handle(
        TransactionGroup $group,
        array $attributes,
        Account $source,
        Account $destination,
        array $tagIds = [],
        int $order = 0,
        string $errorKey = 'transactions.0',
    ): Transaction {
        $this->ensureAccountsFitType($attributes['type'], $source, $destination, $errorKey);

        $currencyId = $this->currencyFor($attributes['type'], $source, $destination, $group);
        $foreignAmount = $attributes['foreign_amount'] ?? null;
        $foreignCurrencyId = $attributes['foreign_currency_id'] ?? null;

        $transaction = $group->transactions()->create([
            'ledger_id' => $group->ledger_id,
            'type' => $attributes['type'],
            'date' => $attributes['date'],
            'description' => $attributes['description'],
            'source_account_id' => $source->id,
            'destination_account_id' => $destination->id,
            'currency_id' => $currencyId,
            'amount' => $attributes['amount'],
            'foreign_currency_id' => $foreignAmount !== null ? $foreignCurrencyId : null,
            'foreign_amount' => $foreignCurrencyId !== null ? $foreignAmount : null,
            'category_id' => $attributes['category_id'] ?? null,
            'notes' => $attributes['notes'] ?? null,
            'order' => $order,
        ]);

        $transaction->postings()->createMany([
            $this->posting($transaction, $source, Money::negate($attributes['amount']), $errorKey),
            $this->posting($transaction, $destination, $attributes['amount'], $errorKey),
        ]);

        $transaction->tags()->sync($tagIds);

        return $transaction;
    }

    private function ensureAccountsFitType(TransactionType $type, Account $source, Account $destination, string $errorKey): void
    {
        if ($source->is($destination)) {
            throw ValidationException::withMessages([
                "{$errorKey}.destination_id" => __('Source and destination must be different accounts.'),
            ]);
        }

        if (! in_array($source->type, $type->allowedSourceTypes(), true)) {
            throw ValidationException::withMessages([
                "{$errorKey}.source_id" => __('A :type cannot come from a :account account.', ['type' => $type->value, 'account' => $source->type->value]),
            ]);
        }

        if (! in_array($destination->type, $type->allowedDestinationTypes(), true)) {
            throw ValidationException::withMessages([
                "{$errorKey}.destination_id" => __('A :type cannot go to a :account account.', ['type' => $type->value, 'account' => $destination->type->value]),
            ]);
        }
    }

    private function currencyFor(TransactionType $type, Account $source, Account $destination, TransactionGroup $group): int
    {
        $primary = $type === TransactionType::Deposit ? $destination : $source;
        $other = $primary->is($source) ? $destination : $source;

        return $primary->currency_id ?? $other->currency_id ?? $group->ledger->primary_currency_id;
    }

    /**
     * @return array{account_id: int, currency_id: int, amount: string}
     */
    private function posting(Transaction $transaction, Account $account, string $amount, string $errorKey): array
    {
        if ($account->currency_id === null || $account->currency_id === $transaction->currency_id) {
            return ['account_id' => $account->id, 'currency_id' => $transaction->currency_id, 'amount' => $amount];
        }

        if ($transaction->foreign_amount === null || $transaction->foreign_currency_id !== $account->currency_id) {
            throw ValidationException::withMessages([
                "{$errorKey}.foreign_amount" => __('Enter the amount in :currency for :account.', [
                    'currency' => $account->currency?->code,
                    'account' => $account->name,
                ]),
            ]);
        }

        $foreign = Money::isNegative($amount) ? Money::negate($transaction->foreign_amount) : $transaction->foreign_amount;

        return ['account_id' => $account->id, 'currency_id' => $account->currency_id, 'amount' => $foreign];
    }
}
