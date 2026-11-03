<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\SyncOpeningBalance;
use App\Actions\ResolveObjectGroup;
use App\Enums\AccountType;
use App\Enums\LiabilityDirection;
use App\Http\Requests\AccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\TransactionGroup;
use App\Models\User;
use App\Support\Money;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function __construct(
        private SyncOpeningBalance $openingBalance,
        private ResolveObjectGroup $objectGroup,
    ) {}

    public function index(Request $request, Ledger $ledger): AnonymousResourceCollection
    {
        $request->validate([
            'type' => ['nullable', 'array'],
            'type.*' => [Rule::in(AccountType::userManaged())],
            'active' => ['nullable', 'boolean'],
        ]);

        return AccountResource::collection(
            $ledger->accounts()
                ->visible()
                ->withBalance()
                ->with('currency', 'objectGroup')
                ->when($request->filled('type'), fn ($query) => $query->whereIn('type', (array) $request->input('type')))
                ->when($request->has('active'), fn ($query) => $query->where('active', $request->boolean('active')))
                ->when($request->filled('search'), fn ($query) => $query->whereLike('name', '%'.$request->string('search').'%'))
                ->orderBy('order')
                ->orderBy('name')
                ->paginate($this->perPage($request)),
        );
    }

    public function store(AccountRequest $request, Ledger $ledger, #[CurrentUser] User $user): JsonResponse
    {
        $account = DB::transaction(function () use ($request, $ledger, $user) {
            $account = $ledger->accounts()->create($this->objectGroup->handle($ledger, $request->accountAttributes()));

            if ($account->type->isOwn() && $request->filled('opening_balance')) {
                $this->openingBalance->handle(
                    $account,
                    $user,
                    $request->string('opening_balance')->value(),
                    $request->string('opening_balance_date')->value(),
                );
            }

            return $account;
        });

        return $this->show($ledger, $account)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Ledger $ledger, Account $account): AccountResource
    {
        abort_if($account->type->isSystem(), 404);

        $account = $ledger->accounts()->withBalance()->with('currency', 'objectGroup')->findOrFail($account->id);

        if ($account->type->isOwn()) {
            $this->attachOpeningBalance($account);
        }

        return new AccountResource($account);
    }

    public function update(AccountRequest $request, Ledger $ledger, Account $account, #[CurrentUser] User $user): AccountResource
    {
        abort_if($account->type->isSystem(), 404);

        DB::transaction(function () use ($request, $ledger, $account, $user) {
            $account->update($this->objectGroup->handle($ledger, $request->accountAttributes()));

            if ($account->type->isOwn() && $request->has('opening_balance')) {
                $this->openingBalance->handle(
                    $account,
                    $user,
                    $request->filled('opening_balance') ? $request->string('opening_balance')->value() : null,
                    $request->filled('opening_balance_date') ? $request->string('opening_balance_date')->value() : null,
                );
            }
        });

        return $this->show($ledger, $account);
    }

    public function destroy(Ledger $ledger, Account $account): Response
    {
        abort_if($account->type->isSystem(), 404);

        DB::transaction(function () use ($account) {
            $this->openingBalance->remove($account);

            TransactionGroup::query()
                ->whereHas('transactions', fn ($query) => $query
                    ->where('source_account_id', $account->id)
                    ->orWhere('destination_account_id', $account->id))
                ->get()
                ->each->deleteWithTransactions();

            $account->delete();
        });

        return response()->noContent();
    }

    private function attachOpeningBalance(Account $account): void
    {
        $opening = SyncOpeningBalance::find($account);
        $amount = null;

        if ($opening !== null) {
            $amount = $opening->destination_account_id === $account->id ? $opening->amount : Money::negate($opening->amount);
            $amount = $account->liability_direction === LiabilityDirection::Owed ? Money::negate($amount) : $amount;
            $amount = Money::round($amount, $account->currency->decimal_places ?? Money::SCALE);
        }

        $account->setAttribute('opening_balance', $amount);
        $account->setAttribute('opening_balance_date', $opening?->date->toDateString());
    }
}
