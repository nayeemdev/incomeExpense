<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccountResource;
use App\Http\Resources\BillResource;
use App\Http\Resources\BudgetResource;
use App\Http\Resources\PiggyBankResource;
use App\Http\Resources\RecurrenceResource;
use App\Http\Resources\RuleResource;
use App\Http\Resources\TransactionResource;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Search\TransactionSearch;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    private const array RELATIONS = ['group', 'sourceAccount.currency', 'destinationAccount.currency', 'currency', 'foreignCurrency', 'category', 'budget', 'bill', 'tags'];

    public function transactions(Request $request, Ledger $ledger, TransactionSearch $search): StreamedResponse
    {
        $filters = $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
            'query' => ['nullable', 'string', 'max:1000'],
        ]);

        $query = Transaction::query()
            ->whereBelongsTo($ledger)
            ->with(self::RELATIONS)
            ->when($filters['start'] ?? null, fn (Builder $query, string $date) => $query->where('date', '>=', $date))
            ->when($filters['end'] ?? null, fn (Builder $query, string $date) => $query->where('date', '<=', $date))
            ->orderBy('date')
            ->orderBy('id');
        $search->apply($query, $search->parse($filters['query'] ?? ''));

        $filename = Str::slug($ledger->name).'-transactions-'.now()->toDateString().'.csv';

        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'date', 'type', 'description', 'amount', 'currency', 'foreign_amount', 'foreign_currency',
                'source', 'destination', 'category', 'budget', 'bill', 'tags', 'notes', 'group_id', 'group_title',
            ]);

            foreach ($query->lazy() as $transaction) {
                fputcsv($output, [
                    $transaction->date->toDateString(),
                    $transaction->type->value,
                    $transaction->description,
                    Money::round($transaction->amount, $transaction->currency->decimal_places),
                    $transaction->currency->code,
                    $transaction->foreign_amount !== null && $transaction->foreignCurrency
                        ? Money::round($transaction->foreign_amount, $transaction->foreignCurrency->decimal_places)
                        : '',
                    $transaction->foreignCurrency->code ?? '',
                    $transaction->sourceAccount->name,
                    $transaction->destinationAccount->name,
                    $transaction->category->name ?? '',
                    $transaction->budget->name ?? '',
                    $transaction->bill->name ?? '',
                    $transaction->tags->pluck('name')->implode(','),
                    $transaction->notes ?? '',
                    $transaction->transaction_group_id,
                    $transaction->group->title ?? '',
                ]);
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Everything in the ledger as one JSON document, for backups or moving to another instance.
     */
    public function backup(Ledger $ledger): JsonResponse
    {
        $ledger->load('primaryCurrency');

        $data = [
            'exported_at' => now()->toIso8601String(),
            'ledger' => ['name' => $ledger->name, 'primary_currency' => $ledger->primaryCurrency->code, 'fiscal_year_start' => $ledger->fiscal_year_start],
            'accounts' => AccountResource::collection($ledger->accounts()->visible()->withBalance()->with('currency', 'objectGroup')->get()),
            'categories' => $ledger->categories()->get(['id', 'name', 'notes']),
            'tags' => $ledger->tags()->get(['id', 'name', 'description', 'date']),
            'budgets' => BudgetResource::collection($ledger->budgets()->with('autoBudgetCurrency', 'limits.currency')->get()),
            'bills' => BillResource::collection($ledger->bills()->with('currency', 'objectGroup')->get()),
            'piggy_banks' => PiggyBankResource::collection($ledger->piggyBanks()->with('currency', 'objectGroup', 'accounts', 'events.account')->get()),
            'recurring_transactions' => RecurrenceResource::collection($ledger->recurrences()->with([
                'repetitions', 'transactions.sourceAccount.currency', 'transactions.destinationAccount.currency',
                'transactions.category', 'transactions.budget', 'transactions.bill',
            ])->get()),
            'rules' => RuleResource::collection($ledger->rules()->with('triggers', 'actions')->get()),
            'transactions' => TransactionResource::collection(Transaction::query()->whereBelongsTo($ledger)->with(self::RELATIONS)->orderBy('date')->orderBy('id')->get()),
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="'.Str::slug($ledger->name).'-backup-'.now()->toDateString().'.json"',
        ]);
    }
}
