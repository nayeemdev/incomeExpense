<?php

namespace App\Listeners;

use App\Actions\Budgets\BudgetSpending;
use App\Enums\TransactionType;
use App\Events\TransactionGroupSaved;
use App\Models\Account;
use App\Models\BudgetLimit;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\BudgetAlertNotification;
use App\Notifications\LargeTransactionNotification;
use App\Notifications\LowBalanceNotification;
use App\Support\Money;
use App\Support\NotificationPreferences;
use App\Support\NotifyOnce;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;

class CheckTransactionAlerts implements ShouldQueue
{
    public function __construct(private BudgetSpending $spending) {}

    public function handle(TransactionGroupSaved $event): void
    {
        $group = $event->group->fresh();

        if ($group === null) {
            return;
        }

        $members = $group->ledger->members()->get();
        $primaryCurrencyId = $group->ledger->primary_currency_id;

        $group->transactions()
            ->with('sourceAccount.currency', 'destinationAccount.currency', 'currency')
            ->get()
            ->each(function (Transaction $transaction) use ($members, $primaryCurrencyId) {
                $this->largeTransaction($transaction, $members, $primaryCurrencyId);
                $this->lowBalance($transaction, $members);
                $this->budget($transaction, $members);
            });
    }

    /**
     * @param  Collection<int, User>  $members
     */
    private function largeTransaction(Transaction $transaction, Collection $members, string $primaryCurrencyId): void
    {
        if ($transaction->type !== TransactionType::Withdrawal || $transaction->currency_id !== $primaryCurrencyId) {
            return;
        }

        $recipients = $members->filter(function (User $user) use ($transaction) {
            $threshold = NotificationPreferences::for($user)->largeTransactionAmount();

            return $threshold !== null && Money::of($transaction->amount) >= Money::of($threshold);
        });

        NotifyOnce::send($recipients, "large_transaction:{$transaction->id}", new LargeTransactionNotification($transaction));
    }

    /**
     * @param  Collection<int, User>  $members
     */
    private function lowBalance(Transaction $transaction, Collection $members): void
    {
        foreach ([$transaction->sourceAccount, $transaction->destinationAccount] as $account) {
            $threshold = $account->getAttribute('low_balance_threshold');

            if (! $account->type->isOwn() || ! is_string($threshold)) {
                continue;
            }

            $balance = (string) (Account::query()->withBalance()->findOrFail($account->id)->balance ?? '0');

            if (Money::of($balance) < Money::of($threshold)) {
                NotifyOnce::send($members, "low_balance:{$account->id}:".now()->toDateString(), new LowBalanceNotification($account, $balance));
            }
        }
    }

    /**
     * @param  Collection<int, User>  $members
     */
    private function budget(Transaction $transaction, Collection $members): void
    {
        $budgetId = $transaction->getAttribute('budget_id');

        if (! is_string($budgetId)) {
            return;
        }

        $limits = BudgetLimit::query()
            ->with('budget', 'currency')
            ->where('budget_id', $budgetId)
            ->where('currency_id', $transaction->currency_id)
            ->whereDate('start_date', '<=', $transaction->date)
            ->whereDate('end_date', '>=', $transaction->date)
            ->get();

        foreach ($limits as $limit) {
            $spent = $this->spending->between([$budgetId], $limit->start_date, $limit->end_date)[$budgetId][$limit->currency_id] ?? '0';
            $amount = Money::of($limit->amount);

            if (Money::of($spent) > $amount) {
                NotifyOnce::send($members, "budget_exceeded:{$limit->id}", new BudgetAlertNotification($limit, $spent, true));
            } elseif (Money::of($spent) >= $amount->mul(config()->integer('ledgerfly.notifications.budget_warning_percentage'))->div(100)) {
                NotifyOnce::send($members, "budget_warning:{$limit->id}", new BudgetAlertNotification($limit, $spent, false));
            }
        }
    }
}
