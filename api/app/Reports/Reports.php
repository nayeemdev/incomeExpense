<?php

namespace App\Reports;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Ledger;
use App\Models\Posting;
use App\Models\Transaction;
use App\Support\CurrencyConverter;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Report figures, always converted to the ledger's primary currency. Amounts in a currency
 * without a known rate are left out and the report is flagged as incomplete.
 */
class Reports
{
    private bool $complete = true;

    /** @var Collection<int, Currency> */
    private Collection $currencies;

    public function __construct(private CurrencyConverter $converter)
    {
        $this->currencies = Currency::query()->get()->keyBy('id');
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    /**
     * Income and expenses per bucket.
     *
     * @param  list<string>  $accountIds
     * @return list<array{start: string, end: string, income: string, expense: string}>
     */
    public function incomeExpense(Ledger $ledger, Period $period, string $interval, array $accountIds = []): array
    {
        $rows = $this->dailyTotals($ledger, $period, $accountIds, ['transactions.type']);
        $result = [];

        foreach ($period->buckets($interval) as $bucket) {
            $income = '0';
            $expense = '0';

            foreach ($rows as $row) {
                if ($row->date < $bucket->start->toDateString() || $row->date > $bucket->end->toDateString()) {
                    continue;
                }

                $amount = $this->toPrimary($ledger, $row->total, $row->currency_id, $bucket->end);

                if ($row->type === TransactionType::Deposit->value) {
                    $income = Money::sum($income, $amount);
                } elseif ($row->type === TransactionType::Withdrawal->value) {
                    $expense = Money::sum($expense, $amount);
                }
            }

            $result[] = [
                'start' => $bucket->start->toDateString(),
                'end' => $bucket->end->toDateString(),
                'income' => $this->round($ledger, $income),
                'expense' => $this->round($ledger, $expense),
            ];
        }

        return $result;
    }

    /**
     * Totals per category, tag, budget or counterparty account, for withdrawals or deposits.
     *
     * @param  'category'|'tag'|'budget'|'counterparty'  $groupBy
     * @param  list<string>  $accountIds
     * @return list<array{id: ?string, name: ?string, total: string, buckets: array<string, string>}>
     */
    public function breakdown(Ledger $ledger, Period $period, string $interval, string $groupBy, TransactionType $type, array $accountIds = []): array
    {
        [$idColumn, $nameColumn, $join] = match ($groupBy) {
            'category' => ['transactions.category_id', 'categories.name', fn (Builder $query) => $query->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')],
            'budget' => ['transactions.budget_id', 'budgets.name', fn (Builder $query) => $query->leftJoin('budgets', 'budgets.id', '=', 'transactions.budget_id')],
            'tag' => ['tags.id', 'tags.name', fn (Builder $query) => $query
                ->leftJoin('tag_transaction', 'tag_transaction.transaction_id', '=', 'transactions.id')
                ->leftJoin('tags', 'tags.id', '=', 'tag_transaction.tag_id')],
            'counterparty' => $type === TransactionType::Withdrawal
                ? ['transactions.destination_account_id', 'counterparties.name', fn (Builder $query) => $query->join('accounts as counterparties', 'counterparties.id', '=', 'transactions.destination_account_id')]
                : ['transactions.source_account_id', 'counterparties.name', fn (Builder $query) => $query->join('accounts as counterparties', 'counterparties.id', '=', 'transactions.source_account_id')],
        };

        $query = $this->transactions($ledger, $period, $accountIds)->where('transactions.type', $type);
        $join($query);
        $rows = $query
            ->groupBy('transactions.date', 'transactions.currency_id', $idColumn, $nameColumn)
            ->select('transactions.date', 'transactions.currency_id', DB::raw("{$idColumn} AS group_id"), DB::raw("{$nameColumn} AS group_name"), DB::raw('SUM(transactions.amount) AS total'))
            ->toBase()
            ->get();

        $buckets = $period->buckets($interval);
        $groups = [];

        foreach ($rows as $row) {
            /** @var object{date: string, currency_id: int, group_id: ?string, group_name: ?string, total: string} $row */
            $bucket = collect($buckets)->first(fn (Period $bucket) => $row->date >= $bucket->start->toDateString() && $row->date <= $bucket->end->toDateString());

            if ($bucket === null) {
                continue;
            }

            $key = (string) ($row->group_id ?? 'none');
            $groups[$key] ??= ['id' => $row->group_id, 'name' => $row->group_name, 'total' => '0', 'buckets' => array_fill_keys(array_map(fn (Period $item) => $item->key(), $buckets), '0')];
            $amount = $this->toPrimary($ledger, $row->total, $row->currency_id, $bucket->end);
            $groups[$key]['total'] = Money::sum($groups[$key]['total'], $amount);
            $groups[$key]['buckets'][$bucket->key()] = Money::sum($groups[$key]['buckets'][$bucket->key()], $amount);
        }

        $result = array_map(fn (array $group) => [
            ...$group,
            'total' => $this->round($ledger, $group['total']),
            'buckets' => array_map(fn (string $amount) => $this->round($ledger, $amount), $group['buckets']),
        ], array_values($groups));

        usort($result, fn (array $a, array $b) => Money::of($b['total']) <=> Money::of($a['total']));

        return $result;
    }

    /**
     * Balance at the end of each bucket for the given own accounts (all own accounts in net worth by default).
     *
     * @param  list<string>  $accountIds
     * @return list<array{date: string, balance: string}>
     */
    public function balanceSeries(Ledger $ledger, Period $period, string $interval, array $accountIds = []): array
    {
        $accounts = $ledger->accounts()
            ->whereIn('type', [AccountType::Asset, AccountType::Liability])
            ->when($accountIds !== [], fn (Builder $query) => $query->whereKey($accountIds), fn (Builder $query) => $query->where('include_net_worth', true))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $opening = $this->postingTotals($accounts, null, $period->start->subDay());
        $daily = Posting::query()
            ->join('transactions', 'transactions.id', '=', 'postings.transaction_id')
            ->whereNull('transactions.deleted_at')
            ->whereIn('postings.account_id', $accounts)
            ->whereBetween('transactions.date', [$period->start->toDateString(), $period->end->toDateString()])
            ->groupBy('transactions.date', 'postings.currency_id')
            ->select('transactions.date', 'postings.currency_id', DB::raw('SUM(postings.amount) AS total'))
            ->orderBy('transactions.date')
            ->toBase()
            ->get();

        $running = $opening;
        $series = [];

        foreach ($period->buckets($interval) as $bucket) {
            foreach ($daily as $row) {
                /** @var object{date: string, currency_id: int, total: string} $row */
                if ($row->date >= $bucket->start->toDateString() && $row->date <= $bucket->end->toDateString()) {
                    $running[$row->currency_id] = Money::sum($running[$row->currency_id] ?? '0', $row->total);
                }
            }

            $total = '0';

            foreach ($running as $currencyId => $amount) {
                $total = Money::sum($total, $this->toPrimary($ledger, $amount, $currencyId, $bucket->end));
            }

            $series[] = ['date' => $bucket->end->toDateString(), 'balance' => $this->round($ledger, $total)];
        }

        return $series;
    }

    /**
     * Start and end balance of each own account over the period, in the account currency.
     *
     * @param  list<string>  $accountIds
     * @return list<array{id: string, name: string, currency: ?string, start: string, end: string, change: string}>
     */
    public function accountChanges(Ledger $ledger, Period $period, array $accountIds = []): array
    {
        $accounts = $ledger->accounts()
            ->with('currency')
            ->whereIn('type', [AccountType::Asset, AccountType::Liability])
            ->where('active', true)
            ->when($accountIds !== [], fn (Builder $query) => $query->whereKey($accountIds))
            ->orderBy('type')
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        $ids = $accounts->map(fn (Account $account) => $account->id)->values()->all();
        $starts = $this->balancesByAccount($ids, $period->start->subDay());
        $ends = $this->balancesByAccount($ids, $period->end);

        return array_values($accounts->map(function (Account $account) use ($starts, $ends) {
            $decimals = $account->currency->decimal_places ?? Money::SCALE;
            $start = $starts[$account->id] ?? '0';
            $end = $ends[$account->id] ?? '0';

            return [
                'id' => $account->id,
                'name' => $account->name,
                'currency' => $account->currency?->code,
                'start' => Money::round($start, $decimals),
                'end' => Money::round($end, $decimals),
                'change' => Money::round(Money::of($end)->sub(Money::of($start))->value, $decimals),
            ];
        })->all());
    }

    /**
     * Budgeted amounts and spending per budget over the period.
     *
     * @return list<array{id: string, name: string, budgeted: string, spent: string}>
     */
    public function budgets(Ledger $ledger, Period $period): array
    {
        $spent = collect($this->breakdown($ledger, $period, 'year', 'budget', TransactionType::Withdrawal))->keyBy('id');

        return array_values($ledger->budgets()
            ->with(['limits' => fn ($query) => $query->whereDate('start_date', '>=', $period->start)->whereDate('end_date', '<=', $period->end)])
            ->orderBy('order')
            ->orderBy('name')
            ->get()
            ->map(function ($budget) use ($ledger, $period, $spent) {
                $budgeted = '0';

                foreach ($budget->limits as $limit) {
                    $budgeted = Money::sum($budgeted, $this->toPrimary($ledger, $limit->amount, $limit->getAttribute('currency_id'), $period->end));
                }

                return [
                    'id' => $budget->id,
                    'name' => $budget->name,
                    'budgeted' => $this->round($ledger, $budgeted),
                    'spent' => $spent->get($budget->id)['total'] ?? $this->round($ledger, '0'),
                ];
            })
            ->all());
    }

    /**
     * @param  list<string>  $accountIds
     * @return Builder<Transaction>
     */
    private function transactions(Ledger $ledger, Period $period, array $accountIds): Builder
    {
        return Transaction::query()
            ->whereBelongsTo($ledger)
            ->whereBetween('transactions.date', [$period->start->toDateString(), $period->end->toDateString()])
            ->when($accountIds !== [], fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereIn('transactions.source_account_id', $accountIds)
                ->orWhereIn('transactions.destination_account_id', $accountIds)));
    }

    /**
     * @param  list<string>  $accountIds
     * @param  list<string>  $extra
     * @return Collection<int, object{date: string, currency_id: int, total: string, type: string}>
     */
    private function dailyTotals(Ledger $ledger, Period $period, array $accountIds, array $extra): Collection
    {
        /** @var Collection<int, object{date: string, currency_id: int, total: string, type: string}> */
        return $this->transactions($ledger, $period, $accountIds)
            ->groupBy('transactions.date', 'transactions.currency_id', ...$extra)
            ->select('transactions.date', 'transactions.currency_id', ...$extra)
            ->addSelect(DB::raw('SUM(transactions.amount) AS total'))
            ->toBase()
            ->get();
    }

    /**
     * @param  array<int, string>  $accountIds
     * @return array<int, string> currency id => total
     */
    private function postingTotals(array $accountIds, ?CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Posting::query()
            ->join('transactions', 'transactions.id', '=', 'postings.transaction_id')
            ->whereNull('transactions.deleted_at')
            ->whereIn('postings.account_id', $accountIds)
            ->when($from, fn (Builder $query, CarbonImmutable $date) => $query->where('transactions.date', '>=', $date->toDateString()))
            ->where('transactions.date', '<=', $to->toDateString())
            ->groupBy('postings.currency_id')
            ->select('postings.currency_id', DB::raw('SUM(postings.amount) AS total'))
            ->toBase()
            ->pluck('total', 'currency_id')
            ->map(fn ($total) => (string) $total)
            ->all();
    }

    /**
     * @param  array<int, string>  $accountIds
     * @return array<int, string> account id => balance
     */
    private function balancesByAccount(array $accountIds, CarbonImmutable $at): array
    {
        return Posting::query()
            ->join('transactions', 'transactions.id', '=', 'postings.transaction_id')
            ->whereNull('transactions.deleted_at')
            ->whereIn('postings.account_id', $accountIds)
            ->where('transactions.date', '<=', $at->toDateString())
            ->groupBy('postings.account_id')
            ->select('postings.account_id', DB::raw('SUM(postings.amount) AS total'))
            ->toBase()
            ->pluck('total', 'account_id')
            ->map(fn ($total) => (string) $total)
            ->all();
    }

    private function toPrimary(Ledger $ledger, string $amount, int $currencyId, CarbonImmutable $date): string
    {
        $converted = $this->converter->convert($amount, $currencyId, $ledger->primary_currency_id, $date, $ledger->id);

        if ($converted === null) {
            $this->complete = false;

            return '0';
        }

        return $converted;
    }

    private function round(Ledger $ledger, string $amount): string
    {
        $currency = $this->currencies->get($ledger->primary_currency_id);

        return Money::round($amount, $currency->decimal_places ?? 2);
    }
}
