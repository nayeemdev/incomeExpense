<?php

namespace App\Actions\Transactions;

use App\Enums\AccountType;
use App\Enums\AssetRole;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Records a transaction from the few fields a widget or shortcut can send. Anything
 * missing is taken from the most recent transaction with the same description, then
 * from the member's default account.
 */
class RecordQuickEntry
{
    public function __construct(private SaveTransactionGroup $saveGroup) {}

    /**
     * @param  array{amount: string, description: string, type?: ?string, account_id?: ?int, counterparty?: ?string, category?: ?string, date?: ?string, notes?: ?string}  $input
     */
    public function handle(Ledger $ledger, User $user, array $input): TransactionGroup
    {
        $type = TransactionType::from($input['type'] ?? TransactionType::Withdrawal->value);
        $previous = $this->previous($ledger, $type, $input['description']);
        $deposit = $type === TransactionType::Deposit;

        $own = $this->ownAccount($ledger, $user, $input['account_id'] ?? null, $previous, $deposit);
        $counterparty = $input['counterparty'] ?? ($deposit ? $previous?->sourceAccount->name : $previous?->destinationAccount->name) ?? $input['description'];

        return $this->saveGroup->handle($ledger, $user, [
            'transactions' => [[
                'type' => $type->value,
                'date' => $input['date'] ?? Carbon::now($user->resolvedPreferences()['timezone'])->toDateString(),
                'description' => $input['description'],
                'amount' => $input['amount'],
                'source_id' => $deposit ? null : $own->id,
                'source_name' => $deposit ? $counterparty : null,
                'destination_id' => $deposit ? $own->id : null,
                'destination_name' => $deposit ? null : $counterparty,
                'category_id' => isset($input['category']) ? null : $previous?->category_id,
                'category_name' => $input['category'] ?? null,
                'tags' => $previous?->tags->pluck('name')->all() ?? [],
                'notes' => $input['notes'] ?? null,
            ]],
        ]);
    }

    private function previous(Ledger $ledger, TransactionType $type, string $description): ?Transaction
    {
        return Transaction::query()
            ->whereBelongsTo($ledger)
            ->where('type', $type)
            ->where('description', $description)
            ->with(['sourceAccount', 'destinationAccount', 'tags'])
            ->latest('date')
            ->latest('id')
            ->first();
    }

    private function ownAccount(Ledger $ledger, User $user, ?int $accountId, ?Transaction $previous, bool $deposit): Account
    {
        $accounts = $ledger->accounts()->where('type', AccountType::Asset)->where('active', true);

        $defaultId = $ledger->membershipOf($user)?->default_account_id;

        $candidates = array_filter([
            $accountId,
            $deposit ? $previous?->destination_account_id : $previous?->source_account_id,
            $defaultId,
        ]);

        foreach ($candidates as $id) {
            if ($account = (clone $accounts)->find($id)) {
                return $account;
            }
        }

        return (clone $accounts)->orderByRaw('asset_role = ? DESC', [AssetRole::Default->value])->orderBy('order')->first()
            ?? throw ValidationException::withMessages(['account_id' => __('Create an asset account first.')]);
    }
}
