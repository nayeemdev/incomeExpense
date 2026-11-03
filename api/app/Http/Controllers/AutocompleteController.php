<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Category;
use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AutocompleteController extends Controller
{
    /**
     * The latest transaction for each matching description, so a client can
     * prefill accounts, category, tags and amount from what was used before.
     */
    public function transactions(Request $request, Ledger $ledger): AnonymousResourceCollection
    {
        $search = $request->validate(['search' => ['required', 'string', 'max:255']])['search'];

        $latestIds = Transaction::query()
            ->whereBelongsTo($ledger)
            ->whereLike('description', "%{$search}%")
            ->groupBy('description')
            ->selectRaw('MAX(id) AS id')
            ->orderByRaw('MAX(date) DESC')
            ->limit(config()->integer('ledgerfly.autocomplete.limit'))
            ->pluck('id');

        return TransactionResource::collection(
            Transaction::query()
                ->whereKey($latestIds)
                ->with(['sourceAccount.currency', 'destinationAccount.currency', 'currency', 'foreignCurrency', 'category', 'budget', 'bill', 'tags'])
                ->orderByDesc('date')
                ->get(),
        );
    }

    /**
     * What this ledger records most often lately, so forms can offer one-tap choices:
     * the latest transaction for each frequent description, frequent categories and payees.
     */
    public function frequent(Request $request, Ledger $ledger): JsonResponse
    {
        $type = TransactionType::from($request->validate([
            'type' => ['nullable', Rule::in(TransactionType::userManaged())],
        ])['type'] ?? TransactionType::Withdrawal->value);
        $limit = config()->integer('ledgerfly.autocomplete.limit');

        $recent = fn () => Transaction::query()
            ->whereBelongsTo($ledger)
            ->where('type', $type)
            ->where('date', '>=', now()->subDays(config()->integer('ledgerfly.autocomplete.frequent_days'))->toDateString());

        $latestIds = $recent()
            ->groupBy('description')
            ->select(DB::raw('MAX(id) AS id'))
            ->orderByRaw('COUNT(*) DESC')
            ->orderByRaw('MAX(id) DESC')
            ->limit($limit)
            ->pluck('id');

        $transactions = Transaction::query()
            ->whereKey($latestIds)
            ->with(['sourceAccount.currency', 'destinationAccount.currency', 'currency', 'foreignCurrency', 'category', 'budget', 'bill', 'tags'])
            ->get()
            ->sortBy(fn (Transaction $transaction) => $latestIds->search($transaction->id))
            ->values();

        $categoryIds = $recent()
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->pluck('category_id');

        $counterpartyColumn = $type === TransactionType::Deposit ? 'source_account_id' : 'destination_account_id';
        $counterpartyIds = $recent()
            ->groupBy($counterpartyColumn)
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->pluck($counterpartyColumn);

        $categories = Category::query()->whereKey($categoryIds)->get(['id', 'name', 'icon', 'color'])->keyBy('id');
        $accounts = Account::query()->whereKey($counterpartyIds)->get(['id', 'name', 'type'])->keyBy('id');

        return response()->json(['data' => [
            'transactions' => TransactionResource::collection($transactions),
            'categories' => $categoryIds->map(fn ($id) => $categories->get($id)?->only('id', 'name', 'icon', 'color'))->filter()->values(),
            'counterparties' => $counterpartyIds->map(fn ($id) => $accounts->get($id)?->only('id', 'name', 'type'))->filter()->values(),
        ]]);
    }
}
