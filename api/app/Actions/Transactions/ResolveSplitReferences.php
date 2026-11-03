<?php

namespace App\Actions\Transactions;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * Resolves the accounts, category, tags and other references of a split, by id or by name.
 */
class ResolveSplitReferences
{
    /**
     * @param  array<string, mixed>  $split
     */
    public function account(Ledger $ledger, TransactionType $type, array $split, string $side, string $errorKey): Account
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
    public function categoryId(Ledger $ledger, array $split): ?int
    {
        if (isset($split['category_id'])) {
            return $ledger->categories()->findOrFail((int) $split['category_id'])->id;
        }

        $name = trim((string) ($split['category_name'] ?? ''));

        return $name === '' ? null : $ledger->categories()->firstOrCreate(['name' => $name])->id;
    }

    /**
     * @param  HasMany<covariant Model, Ledger>  $relation
     */
    public function ownedId(HasMany $relation, mixed $id, string $errorKey): ?int
    {
        if ($id === null) {
            return null;
        }

        if (! $relation->whereKey((int) $id)->exists()) {
            throw ValidationException::withMessages([$errorKey => __('This item does not belong to the ledger.')]);
        }

        return (int) $id;
    }

    /**
     * @param  list<?string>  $names
     * @return list<int>
     */
    public function tagIds(Ledger $ledger, array $names): array
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
