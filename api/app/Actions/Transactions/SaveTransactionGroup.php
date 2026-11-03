<?php

namespace App\Actions\Transactions;

use App\Enums\TransactionType;
use App\Events\TransactionGroupSaved;
use App\Models\Ledger;
use App\Models\TransactionGroup;
use App\Models\User;
use App\RuleEngine\RuleEngine;
use Illuminate\Support\Facades\DB;

class SaveTransactionGroup
{
    public function __construct(
        private WriteTransaction $writer,
        private ResolveSplitReferences $references,
        private RuleEngine $rules,
    ) {}

    /**
     * @param  array{title?: ?string, transactions: list<array<string, mixed>>}  $data
     * @param  array<string, mixed>  $groupAttributes
     */
    public function handle(Ledger $ledger, ?User $user, array $data, ?TransactionGroup $group = null, array $groupAttributes = [], bool $applyRules = true): TransactionGroup
    {
        $created = $group === null;

        $group = DB::transaction(function () use ($ledger, $user, $data, $group, $groupAttributes) {
            if ($group === null) {
                $group = $ledger->transactionGroups()->create([
                    ...$groupAttributes,
                    'title' => $data['title'] ?? null,
                    'created_by' => $user?->id,
                ]);
            } else {
                $group->update(['title' => $data['title'] ?? null]);
                $group->transactions()->withTrashed()->get()->each->forceDelete();
            }

            foreach ($data['transactions'] as $index => $split) {
                $errorKey = "transactions.{$index}";
                $type = TransactionType::from($split['type']);

                $this->writer->handle(
                    $group,
                    [
                        'type' => $type,
                        'date' => $split['date'],
                        'description' => $split['description'],
                        'amount' => $split['amount'],
                        'foreign_amount' => $split['foreign_amount'] ?? null,
                        'foreign_currency_id' => $split['foreign_currency_id'] ?? null,
                        'category_id' => $this->references->categoryId($ledger, $split),
                        'budget_id' => $this->references->ownedId($ledger->budgets(), $split['budget_id'] ?? null, "{$errorKey}.budget_id"),
                        'bill_id' => $this->references->ownedId($ledger->bills(), $split['bill_id'] ?? null, "{$errorKey}.bill_id"),
                        'notes' => $split['notes'] ?? null,
                    ],
                    $this->references->account($ledger, $type, $split, 'source', $errorKey),
                    $this->references->account($ledger, $type, $split, 'destination', $errorKey),
                    $this->references->tagIds($ledger, $split['tags'] ?? []),
                    $index,
                    $errorKey,
                );
            }

            return $group;
        });

        if ($applyRules) {
            $this->rules->applyToGroup($group, $created);
        }

        TransactionGroupSaved::dispatch($group, $created);

        return $group;
    }
}
