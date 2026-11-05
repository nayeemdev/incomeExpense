<?php

namespace App\Actions\Accounts;

use App\Models\Account;
use App\Models\Investment;
use App\Models\TransactionGroup;
use Illuminate\Support\Facades\DB;

class DeleteAccount
{
    public function __construct(private SyncOpeningBalance $openingBalance) {}

    public function handle(Account $account): void
    {
        DB::transaction(function () use ($account) {
            $this->openingBalance->remove($account);

            TransactionGroup::query()
                ->whereHas('transactions', fn ($query) => $query
                    ->where('source_account_id', $account->id)
                    ->orWhere('destination_account_id', $account->id))
                ->get()
                ->each->deleteWithTransactions();

            Investment::query()->where('account_id', $account->id)->delete();
            $account->delete();
        });
    }
}
