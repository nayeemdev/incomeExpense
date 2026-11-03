<?php

namespace App\Actions\Accounts;

use App\Actions\Transactions\WriteTransaction;
use App\Enums\AccountType;
use App\Enums\LiabilityDirection;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;

class SyncOpeningBalance
{
    public function __construct(private WriteTransaction $writer) {}

    /**
     * For liabilities the amount is what is owed (or lent), entered as a positive number.
     */
    public function handle(Account $account, User $user, ?string $amount, ?string $date): void
    {
        $counterpart = $this->detach($account);

        if ($amount === null || $date === null || Money::isZero($amount)) {
            $counterpart?->forceDelete();

            return;
        }

        $counterpart ??= $account->ledger->accounts()->create([
            'type' => AccountType::InitialBalance,
            'name' => $account->name,
            'currency_id' => $account->currency_id,
        ]);
        $counterpart->update(['name' => $account->name, 'currency_id' => $account->currency_id]);

        $signed = $account->liability_direction === LiabilityDirection::Owed ? Money::negate($amount) : $amount;
        $incoming = ! Money::isNegative($signed);

        $group = $account->ledger->transactionGroups()->create(['created_by' => $user->id]);

        $this->writer->handle(
            $group,
            [
                'type' => TransactionType::OpeningBalance,
                'date' => $date,
                'description' => __('Opening balance'),
                'amount' => Money::abs($signed),
            ],
            $incoming ? $counterpart : $account,
            $incoming ? $account : $counterpart,
            errorKey: 'opening_balance',
        );
    }

    public function remove(Account $account): void
    {
        $this->detach($account)?->forceDelete();
    }

    public static function find(Account $account): ?Transaction
    {
        return Transaction::query()
            ->where('type', TransactionType::OpeningBalance)
            ->where(fn ($query) => $query
                ->where('source_account_id', $account->id)
                ->orWhere('destination_account_id', $account->id))
            ->first();
    }

    private function detach(Account $account): ?Account
    {
        $existing = self::find($account);

        if ($existing === null) {
            return null;
        }

        $counterpart = $existing->source_account_id === $account->id ? $existing->destinationAccount : $existing->sourceAccount;
        $existing->group->forceDelete();

        return $counterpart;
    }
}
