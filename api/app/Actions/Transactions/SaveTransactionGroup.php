<?php

namespace App\Actions\Transactions;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\TransactionGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveTransactionGroup
{
    public function __construct(private WriteTransaction $writer) {}

    /**
     * @param  array{title?: ?string, transactions: list<array<string, mixed>>}  $data
     */
    public function handle(Ledger $ledger, User $user, array $data, ?TransactionGroup $group = null): TransactionGroup
    {
        return DB::transaction(function () use ($ledger, $user, $data, $group) {
            if ($group === null) {
                $group = $ledger->transactionGroups()->create([
                    'title' => $data['title'] ?? null,
                    'created_by' => $user->id,
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
                        'category_id' => $this->categoryId($ledger, $split),
                        'notes' => $split['notes'] ?? null,
                    ],
                    $this->account($ledger, $type, $split, 'source', $errorKey),
                    $this->account($ledger, $type, $split, 'destination', $errorKey),
                    $this->tagIds($ledger, $split['tags'] ?? []),
                    $index,
                    $errorKey,
                );
            }

            return $group;
        });
    }

    /**
     * @param  array<string, mixed>  $split
     */
    private function account(Ledger $ledger, TransactionType $type, array $split, string $side, string $errorKey): Account
    {
        $allowed = $side === 'source' ? $type->allowedSourceTypes() : $type->allowedDestinationTypes();
        $id = $split["{$side}_id"] ?? null;
        $name = isset($split["{$side}_name"]) ? trim((string) $split["{$side}_name"]) : '';

        if ($id !== null) {
            $account = $ledger->accounts()->whereIn('type', $allowed)->find((int) $id);

            if ($account === null) {
                throw ValidationException::withMessages([
                    "{$errorKey}.{$side}_id" => __('This account cannot be used here.'),
                ]);
            }

            return $account;
        }

        $nameableType = match (true) {
            $side === 'destination' && $type === TransactionType::Withdrawal => AccountType::Expense,
            $side === 'source' && $type === TransactionType::Deposit => AccountType::Revenue,
            default => null,
        };

        if ($nameableType === null || $name === '') {
            throw ValidationException::withMessages([
                "{$errorKey}.{$side}_id" => __('Choose an account.'),
            ]);
        }

        return $ledger->accounts()->whereIn('type', $allowed)->where('name', $name)->first()
            ?? $ledger->accounts()->create(['type' => $nameableType, 'name' => $name]);
    }

    /**
     * @param  array<string, mixed>  $split
     */
    private function categoryId(Ledger $ledger, array $split): ?int
    {
        if (isset($split['category_id'])) {
            return $ledger->categories()->findOrFail((int) $split['category_id'])->id;
        }

        $name = trim((string) ($split['category_name'] ?? ''));

        return $name === '' ? null : $ledger->categories()->firstOrCreate(['name' => $name])->id;
    }

    /**
     * @param  list<?string>  $names
     * @return list<int>
     */
    private function tagIds(Ledger $ledger, array $names): array
    {
        $ids = [];

        foreach ($names as $name) {
            $name = trim((string) $name);

            if ($name !== '') {
                $ids[] = $ledger->tags()->firstOrCreate(['name' => $name])->id;
            }
        }

        return array_values(array_unique($ids));
    }
}
