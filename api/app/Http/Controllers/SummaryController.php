<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\TransactionType;
use App\Http\Resources\AccountResource;
use App\Models\Currency;
use App\Models\Ledger;
use App\Models\Posting;
use App\Models\Transaction;
use App\Support\CurrencyConverter;
use App\Support\Money;
use Carbon\CarbonImmutable;
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

    public function __construct(private CurrencyConverter $converter) {}

    public function __invoke(Request $request, Ledger $ledger): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ]);
        $start = Carbon::parse($validated['start'])->toDateString();
        $end = Carbon::parse($validated['end'])->toDateString();
        $this->currencies = Currency::query()->get()->keyBy('id');

        $netWorth = $this->netWorth($ledger, $end);
        $spent = $this->totalByType($ledger, TransactionType::Withdrawal, $start, $end);
        $earned = $this->totalByType($ledger, TransactionType::Deposit, $start, $end);
        $date = CarbonImmutable::parse($end);

        return response()->json(['data' => [
            'net_worth' => $netWorth,
            'spent' => $spent,
            'earned' => $earned,
            'net_worth_total' => $this->inPrimary($ledger, $netWorth, $date),
            'spent_total' => $this->inPrimary($ledger, $spent, $date),
            'earned_total' => $this->inPrimary($ledger, $earned, $date),
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
     * Adds up amounts in several currencies in the ledger's primary currency. When a rate
     * is missing the total leaves that currency out and says it is incomplete.
     *
     * @param  list<array{currency: string, amount: string}>  $amounts
     * @return array{currency: string, amount: string, complete: bool}
     */
    private function inPrimary(Ledger $ledger, array $amounts, CarbonImmutable $date): array
    {
        $primary = $this->currencies->get($ledger->primary_currency_id) ?? Currency::query()->findOrFail($ledger->primary_currency_id);
        $byCode = $this->currencies->keyBy('code');
        $total = '0';
        $complete = true;

        foreach ($amounts as $item) {
            $currency = $byCode->get($item['currency']);
            $converted = $currency ? $this->converter->convert($item['amount'], $currency->id, $primary->id, $date, $ledger->id) : null;

            if ($converted === null) {
                $complete = false;

                continue;
            }

            $total = Money::sum($total, $converted);
        }

        return ['currency' => $primary->code, 'amount' => Money::round($total, $primary->decimal_places), 'complete' => $complete];
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
