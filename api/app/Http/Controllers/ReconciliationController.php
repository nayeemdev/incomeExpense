<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\WriteTransaction;
use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;
use App\Rules\Amount;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReconciliationController extends Controller
{
    /**
     * Matches the account to a statement balance by recording the difference as a
     * reconciliation transaction on that date.
     */
    public function __invoke(Request $request, Ledger $ledger, Account $account, #[CurrentUser] User $user, WriteTransaction $writer): AccountResource
    {
        abort_unless($account->type->isOwn(), 404);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'balance' => ['required', Amount::signed()],
        ]);
        $date = CarbonImmutable::parse($validated['date']);
        $current = (string) (Account::query()->withBalance($date)->findOrFail($account->id)->balance ?? '0');
        $difference = Money::of($validated['balance'])->sub(Money::of($current))->value;

        if (! Money::isZero($difference)) {
            DB::transaction(function () use ($ledger, $account, $user, $writer, $difference, $date) {
                $counterpart = $ledger->accounts()->firstOrCreate(
                    ['type' => AccountType::Reconciliation, 'name' => $account->name, 'currency_id' => $account->currency_id],
                );
                $incoming = ! Money::isNegative($difference);
                $group = $ledger->transactionGroups()->create(['created_by' => $user->id]);

                $writer->handle(
                    $group,
                    [
                        'type' => TransactionType::Reconciliation,
                        'date' => $date->toDateString(),
                        'description' => __('Reconciliation'),
                        'amount' => Money::abs($difference),
                    ],
                    $incoming ? $counterpart : $account,
                    $incoming ? $account : $counterpart,
                    errorKey: 'balance',
                );
            });
        }

        return new AccountResource($ledger->accounts()->withBalance()->with('currency')->findOrFail($account->id));
    }
}
