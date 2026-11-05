<?php

namespace App\Actions\Investments;

use App\Actions\Accounts\SyncOpeningBalance;
use App\Actions\Transactions\SaveTransactionGroup;
use App\Enums\InvestmentEventType;
use App\Enums\TransactionType;
use App\Models\Investment;
use App\Models\InvestmentEvent;
use App\Models\TransactionGroup;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a buy, sale, payout or new valuation, and books the money that moves with it so that
 * account balances and reports stay in step with the investment.
 */
class RecordInvestmentEvent
{
    public function __construct(
        private SaveTransactionGroup $transactions,
        private SyncOpeningBalance $openingBalance,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Investment $investment, User $user, array $data): InvestmentEvent
    {
        $type = InvestmentEventType::from((string) $data['type']);
        $date = CarbonImmutable::parse((string) $data['date']);
        $units = $investment->kind->tracksUnits() && isset($data['units']) ? (string) $data['units'] : null;
        $close = ! $investment->kind->tracksUnits() && ($data['close'] ?? false);

        return DB::transaction(function () use ($investment, $user, $data, $type, $date, $units, $close) {
            $position = $investment->position($date);

            $event = match ($type) {
                InvestmentEventType::Buy => $this->buy($investment, $user, $data, $date, $units),
                InvestmentEventType::Sell => $this->sell($investment, $user, $data, $date, $units, $position->costOf((string) $data['amount'], $units, $close)),
                InvestmentEventType::Income => $this->income($investment, $user, $data, $date),
                InvestmentEventType::Valuation => $this->valuation($investment, $data, $date, $position->units),
            };

            $this->syncClosed($investment, $type === InvestmentEventType::Sell ? $date : null);

            return $event;
        });
    }

    public function delete(InvestmentEvent $event): void
    {
        DB::transaction(function () use ($event) {
            $event->transactionGroup?->deleteWithTransactions();
            $event->delete();

            $this->syncClosed($event->investment, null);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buy(Investment $investment, User $user, array $data, CarbonImmutable $date, ?string $units): InvestmentEvent
    {
        $amount = (string) $data['amount'];
        $account = $investment->account;

        if (empty($data['account_id'])) {
            if (SyncOpeningBalance::find($account) !== null) {
                throw ValidationException::withMessages(['account_id' => __('Choose the account the money came from.')]);
            }

            $this->openingBalance->handle($account, $user, $amount, $date->toDateString());
            $group = SyncOpeningBalance::find($account)?->group;
        } else {
            $group = $this->book($investment, $user, $date, __('Bought :name', ['name' => $account->name]), [[
                'type' => TransactionType::Transfer->value,
                'amount' => $amount,
                'source_id' => $data['account_id'],
                'destination_id' => $account->id,
            ]]);
        }

        return $investment->events()->create([
            'transaction_group_id' => $group?->id,
            'type' => InvestmentEventType::Buy,
            'date' => $date,
            'units' => $units,
            'price' => $units !== null ? Money::of($amount)->div(Money::of($units), Money::SCALE)->value : null,
            'amount' => $amount,
        ]);
    }

    /**
     * The proceeds land in the chosen account. The difference with the cost basis is booked as a
     * gain or a loss, so the investment account keeps holding exactly what is still invested.
     *
     * @param  array<string, mixed>  $data
     */
    private function sell(Investment $investment, User $user, array $data, CarbonImmutable $date, ?string $units, string $cost): InvestmentEvent
    {
        $amount = (string) $data['amount'];
        $account = $investment->account;
        $gain = Money::of($amount)->sub(Money::of($cost))->value;

        $splits = [[
            'type' => TransactionType::Transfer->value,
            'amount' => $amount,
            'source_id' => $account->id,
            'destination_id' => $data['account_id'],
        ]];

        if (! Money::isZero(Money::round($gain, $investment->currency()->decimal_places))) {
            $splits[] = Money::isNegative($gain)
                ? ['type' => TransactionType::Withdrawal->value, 'amount' => Money::abs($gain), 'source_id' => $account->id, 'destination_name' => __('Investment losses'), 'category_name' => __('Investments')]
                : ['type' => TransactionType::Deposit->value, 'amount' => $gain, 'source_name' => __('Investment gains'), 'destination_id' => $account->id, 'category_name' => __('Investments')];
        }

        $group = $this->book($investment, $user, $date, __('Sold :name', ['name' => $account->name]), $splits);

        return $investment->events()->create([
            'transaction_group_id' => $group->id,
            'type' => InvestmentEventType::Sell,
            'date' => $date,
            'units' => $units,
            'price' => $units !== null ? Money::of($amount)->div(Money::of($units), Money::SCALE)->value : null,
            'amount' => $amount,
            'cost' => $cost,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function income(Investment $investment, User $user, array $data, CarbonImmutable $date): InvestmentEvent
    {
        $amount = (string) $data['amount'];
        $name = $investment->account->name;

        $group = $this->book($investment, $user, $date, __('Income from :name', ['name' => $name]), [[
            'type' => TransactionType::Deposit->value,
            'amount' => $amount,
            'source_name' => $name,
            'destination_id' => $data['account_id'],
            'category_name' => __('Investment income'),
        ]]);

        return $investment->events()->create([
            'transaction_group_id' => $group->id,
            'type' => InvestmentEventType::Income,
            'date' => $date,
            'amount' => $amount,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function valuation(Investment $investment, array $data, CarbonImmutable $date, string $units): InvestmentEvent
    {
        $price = $investment->kind->tracksUnits() ? (string) $data['price'] : null;

        return $investment->events()->create([
            'type' => InvestmentEventType::Valuation,
            'date' => $date,
            'price' => $price,
            'amount' => $price !== null ? Money::of($units)->mul(Money::of($price), Money::SCALE)->value : (string) $data['amount'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $splits
     */
    private function book(Investment $investment, User $user, CarbonImmutable $date, string $description, array $splits): TransactionGroup
    {
        $splits = array_map(fn (array $split) => [...$split, 'date' => $date->toDateString(), 'description' => $description], $splits);

        return $this->transactions->handle($investment->ledger, $user, ['transactions' => $splits], applyRules: false);
    }

    private function syncClosed(Investment $investment, ?CarbonImmutable $soldOn): void
    {
        $investment->unsetRelation('events');
        $closed = ! $investment->position()->isOpen() && $investment->events->contains('type', InvestmentEventType::Sell);

        $investment->update(['closed_at' => $closed ? ($soldOn ?? $investment->closed_at ?? CarbonImmutable::today()) : null]);
    }
}
