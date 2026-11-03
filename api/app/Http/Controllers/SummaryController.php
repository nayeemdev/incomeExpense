<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Http\Resources\AccountResource;
use App\Models\Currency;
use App\Models\Ledger;
use App\Models\Posting;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SummaryController extends Controller
{
    /** @var Collection<int, Currency> */
    private Collection $currencies;

    public function __invoke(Request $request, Ledger $ledger): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ]);
        $start = Carbon::parse($validated['start'])->toDateString();
        $end = Carbon::parse($validated['end'])->toDateString();
        $this->currencies = Currency::query()->get()->keyBy('id');

        return response()->json(['data' => [
            'net_worth' => $this->netWorth($ledger, $end),
            'spent' => $this->totalByType($ledger, TransactionType::Withdrawal, $start, $end),
            'earned' => $this->totalByType($ledger, TransactionType::Deposit, $start, $end),
            'top_categories' => $this->topCategories($ledger, $start, $end),
            'accounts' => AccountResource::collection(
                $ledger->accounts()
                    ->where('type', AccountType::Asset)
                    ->where('active', true)
                    ->withBalance()
                    ->with('currency')
                    ->orderBy('order')
                    ->orderBy('name')
                    ->get(),
            ),
        ]]);
    }

    /**
     * @return list<array{currency: string, amount: string}>
     */
    private function netWorth(Ledger $ledger, string $end): array
    {
        return $this->amountsByCurrency(Posting::query()
            ->join('transactions', 'transactions.id', '=', 'postings.transaction_id')
            ->join('accounts', 'accounts.id', '=', 'postings.account_id')
            ->where('accounts.ledger_id', $ledger->id)
            ->whereIn('accounts.type', [AccountType::Asset, AccountType::Liability])
            ->where('accounts.include_net_worth', true)
            ->whereNull('accounts.deleted_at')
            ->whereNull('transactions.deleted_at')
            ->where('transactions.date', '<=', $end)
            ->groupBy('postings.currency_id')
            ->select('postings.currency_id', DB::raw('SUM(postings.amount) AS total')));
    }

    /**
     * @return list<array{currency: string, amount: string}>
     */
    private function totalByType(Ledger $ledger, TransactionType $type, string $start, string $end): array
    {
        return $this->amountsByCurrency(Transaction::query()
            ->whereBelongsTo($ledger)
            ->where('type', $type)
            ->whereBetween('date', [$start, $end])
            ->groupBy('currency_id')
            ->select('currency_id', DB::raw('SUM(amount) AS total')));
    }

    /**
     * @return list<array{id: ?int, name: ?string, currency: string, amount: string}>
     */
    private function topCategories(Ledger $ledger, string $start, string $end): array
    {
        $rows = Transaction::query()
            ->whereBelongsTo($ledger)
            ->where('transactions.type', TransactionType::Withdrawal)
            ->whereBetween('transactions.date', [$start, $end])
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->groupBy('transactions.category_id', 'categories.name', 'transactions.currency_id')
            ->select('transactions.category_id', 'categories.name', 'transactions.currency_id', DB::raw('SUM(transactions.amount) AS total'))
            ->orderByDesc('total')
            ->limit(config()->integer('ledgerfly.summary.top_categories'))
            ->toBase()
            ->get();

        $result = [];

        foreach ($rows as $row) {
            /** @var object{category_id: ?int, name: ?string, currency_id: int, total: string} $row */
            $result[] = [
                'id' => $row->category_id,
                'name' => $row->name,
                ...$this->amount($row->currency_id, $row->total),
            ];
        }

        return $result;
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return list<array{currency: string, amount: string}>
     */
    private function amountsByCurrency(Builder $query): array
    {
        $result = [];

        foreach ($query->toBase()->get() as $row) {
            /** @var object{currency_id: int, total: string} $row */
            $result[] = $this->amount($row->currency_id, $row->total);
        }

        return $result;
    }

    /**
     * @return array{currency: string, amount: string}
     */
    private function amount(int $currencyId, string $total): array
    {
        $currency = $this->currencies->get($currencyId) ?? Currency::query()->findOrFail($currencyId);

        return ['currency' => $currency->code, 'amount' => Money::round($total, $currency->decimal_places)];
    }
}
