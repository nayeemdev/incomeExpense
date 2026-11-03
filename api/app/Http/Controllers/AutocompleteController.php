<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Models\Ledger;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
                ->with(['sourceAccount.currency', 'destinationAccount.currency', 'currency', 'foreignCurrency', 'category', 'tags'])
                ->orderByDesc('date')
                ->get(),
        );
    }
}
