<?php

namespace App\Http\Controllers;

use App\Actions\Transactions\SaveTransactionGroup;
use App\Enums\TransactionType;
use App\Http\Requests\TransactionGroupRequest;
use App\Http\Resources\TransactionGroupResource;
use App\Http\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\Posting;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use App\Models\User;
use App\Rules\Amount;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function __construct(private SaveTransactionGroup $saveGroup) {}

    public function index(Request $request, Ledger $ledger): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'account_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'tag_id' => ['nullable', 'integer'],
            'budget_id' => ['nullable', 'integer'],
            'bill_id' => ['nullable', 'integer'],
            'type' => ['nullable', 'array'],
            'type.*' => [Rule::enum(TransactionType::class)],
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
            'search' => ['nullable', 'string', 'max:255'],
            'min_amount' => ['nullable', Amount::positive()],
            'max_amount' => ['nullable', Amount::positive()],
        ]);

        $account = isset($filters['account_id']) ? $ledger->accounts()->findOrFail((int) $filters['account_id']) : null;

        $query = Transaction::query()
            ->whereBelongsTo($ledger)
            ->select('transactions.*')
            ->with(['group', 'sourceAccount.currency', 'destinationAccount.currency', 'currency', 'foreignCurrency', 'category', 'budget', 'bill', 'tags'])
            ->when($account, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('source_account_id', $account?->id)
                ->orWhere('destination_account_id', $account?->id)))
            ->when($filters['category_id'] ?? null, fn (Builder $query, int $id) => $query->where('category_id', $id))
            ->when($filters['budget_id'] ?? null, fn (Builder $query, int $id) => $query->where('budget_id', $id))
            ->when($filters['bill_id'] ?? null, fn (Builder $query, int $id) => $query->where('bill_id', $id))
            ->when($filters['tag_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('tags', fn (Builder $query) => $query->whereKey($id)))
            ->when($filters['type'] ?? null, fn (Builder $query, array $types) => $query->whereIn('type', $types))
            ->when($filters['start'] ?? null, fn (Builder $query, string $start) => $query->where('date', '>=', $start))
            ->when($filters['end'] ?? null, fn (Builder $query, string $end) => $query->where('date', '<=', $end))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->whereLike('description', "%{$search}%")
                ->orWhereLike('notes', "%{$search}%")))
            ->when($filters['min_amount'] ?? null, fn (Builder $query, string $min) => $query->where('amount', '>=', $min))
            ->when($filters['max_amount'] ?? null, fn (Builder $query, string $max) => $query->where('amount', '<=', $max))
            ->orderByDesc('date')
            ->orderByDesc('transactions.id');

        if ($account?->type->isOwn()) {
            $this->withRunningBalance($query, $account);
        }

        return TransactionResource::collection($query->paginate($this->perPage($request)));
    }

    public function store(TransactionGroupRequest $request, Ledger $ledger, #[CurrentUser] User $user): TransactionGroupResource
    {
        /** @var array{title?: ?string, transactions: list<array<string, mixed>>} $data */
        $data = $request->validated();

        return $this->show($ledger, $this->saveGroup->handle($ledger, $user, $data));
    }

    public function show(Ledger $ledger, TransactionGroup $transactionGroup): TransactionGroupResource
    {
        return new TransactionGroupResource($transactionGroup->load([
            'creator',
            'transactions.sourceAccount.currency',
            'transactions.destinationAccount.currency',
            'transactions.currency',
            'transactions.foreignCurrency',
            'transactions.category',
            'transactions.budget',
            'transactions.bill',
            'transactions.tags',
        ]));
    }

    public function update(TransactionGroupRequest $request, Ledger $ledger, TransactionGroup $transactionGroup, #[CurrentUser] User $user): TransactionGroupResource
    {
        abort_if($this->isSystemGroup($transactionGroup), 403, __('This transaction is managed by its account.'));

        /** @var array{title?: ?string, transactions: list<array<string, mixed>>} $data */
        $data = $request->validated();

        return $this->show($ledger, $this->saveGroup->handle($ledger, $user, $data, $transactionGroup));
    }

    public function destroy(Ledger $ledger, TransactionGroup $transactionGroup): Response
    {
        abort_if($this->isSystemGroup($transactionGroup), 403, __('This transaction is managed by its account.'));

        $transactionGroup->deleteWithTransactions();

        return response()->noContent();
    }

    public function restore(Ledger $ledger, int $group): TransactionGroupResource
    {
        $transactionGroup = $ledger->transactionGroups()->onlyTrashed()->findOrFail($group);
        $transactionGroup->restoreWithTransactions();

        return $this->show($ledger, $transactionGroup);
    }

    /**
     * @param  Builder<Transaction>  $query
     */
    private function withRunningBalance(Builder $query, Account $account): void
    {
        $balances = Posting::query()
            ->select('postings.transaction_id')
            ->selectRaw('SUM(postings.amount) OVER (ORDER BY transactions.date, transactions.id) AS running_balance')
            ->join('transactions', 'transactions.id', '=', 'postings.transaction_id')
            ->whereNull('transactions.deleted_at')
            ->where('postings.account_id', $account->id);

        $query->joinSub($balances, 'balances', 'balances.transaction_id', '=', 'transactions.id')
            ->addSelect('balances.running_balance');
    }

    private function isSystemGroup(TransactionGroup $group): bool
    {
        return $group->transactions()
            ->whereNotIn('type', TransactionType::userManaged())
            ->exists();
    }
}
