<?php

namespace App\Http\Controllers;

use App\Actions\ResolveObjectGroup;
use App\Enums\AccountType;
use App\Http\Resources\PiggyBankResource;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\PiggyBank;
use App\Notifications\PiggyBankReachedNotification;
use App\Rules\Amount;
use App\Support\Money;
use App\Support\NotifyOnce;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PiggyBankController extends Controller
{
    public function __construct(private ResolveObjectGroup $objectGroup) {}

    public function index(Request $request, Ledger $ledger): AnonymousResourceCollection
    {
        return PiggyBankResource::collection(
            $ledger->piggyBanks()
                ->with('currency', 'objectGroup', 'accounts')
                ->when($request->has('active'), fn (Builder $query) => $query->where('active', $request->boolean('active')))
                ->orderBy('order')
                ->orderBy('name')
                ->get(),
        );
    }

    public function store(Request $request, Ledger $ledger): JsonResponse
    {
        $validated = $this->validated($request, $ledger);

        $piggyBank = DB::transaction(function () use ($ledger, $validated) {
            $piggyBank = $ledger->piggyBanks()->create($this->objectGroup->handle($ledger, collect($validated)->except('account_ids')->all()));
            $piggyBank->accounts()->sync($validated['account_ids']);

            return $piggyBank;
        });

        return (new PiggyBankResource($this->load($piggyBank)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Ledger $ledger, PiggyBank $piggyBank): PiggyBankResource
    {
        return new PiggyBankResource($this->load($piggyBank));
    }

    public function update(Request $request, Ledger $ledger, PiggyBank $piggyBank): PiggyBankResource
    {
        $validated = $this->validated($request, $ledger, $piggyBank);

        DB::transaction(function () use ($ledger, $piggyBank, $validated) {
            $piggyBank->update($this->objectGroup->handle($ledger, collect($validated)->except('account_ids')->all()));

            if (isset($validated['account_ids'])) {
                $piggyBank->accounts()->sync($validated['account_ids']);
            }
        });

        return new PiggyBankResource($this->load($piggyBank));
    }

    public function destroy(Ledger $ledger, PiggyBank $piggyBank): Response
    {
        $piggyBank->delete();

        return response()->noContent();
    }

    /**
     * Moves money into (positive amount) or out of (negative amount) the piggy bank for one account.
     */
    public function move(Request $request, Ledger $ledger, PiggyBank $piggyBank): PiggyBankResource
    {
        $validated = $request->validate([
            'account_id' => ['required', 'ulid'],
            'amount' => ['required', Amount::signed()],
            'date' => ['nullable', 'date'],
        ]);

        /** @var Account|null $account */
        $account = $piggyBank->accounts()->whereKey($validated['account_id'])->first();

        if ($account === null) {
            throw ValidationException::withMessages(['account_id' => __('This account is not linked to the piggy bank.')]);
        }

        $amount = $validated['amount'];
        $current = (string) $account->getRelation('pivot')->getAttribute('current_amount');
        $next = Money::sum($current, $amount);

        if (Money::isNegative($next)) {
            throw ValidationException::withMessages(['amount' => __('You cannot take out more than is saved from this account.')]);
        }

        if (! Money::isNegative($amount)) {
            $this->ensureCanAdd($piggyBank, $account, $amount);
        }

        DB::transaction(function () use ($piggyBank, $account, $amount, $next, $validated) {
            $piggyBank->accounts()->updateExistingPivot($account->id, ['current_amount' => $next]);
            $piggyBank->events()->create([
                'account_id' => $account->id,
                'amount' => $amount,
                'date' => $validated['date'] ?? now()->toDateString(),
            ]);
        });

        $this->notifyIfReached($piggyBank);

        return new PiggyBankResource($this->load($piggyBank));
    }

    private function notifyIfReached(PiggyBank $piggyBank): void
    {
        if ($piggyBank->target_amount === null) {
            return;
        }

        $saved = (string) DB::table('account_piggy_bank')->where('piggy_bank_id', $piggyBank->id)->sum('current_amount');

        if (Money::of($saved) >= Money::of($piggyBank->target_amount)) {
            NotifyOnce::send($piggyBank->ledger->members()->get(), "piggy_bank_reached:{$piggyBank->id}", new PiggyBankReachedNotification($piggyBank));
        }
    }

    private function ensureCanAdd(PiggyBank $piggyBank, Account $account, string $amount): void
    {
        $saved = Money::sum(...$piggyBank->accounts()->get()->map(fn (Account $item) => (string) $item->getRelation('pivot')->getAttribute('current_amount'))->all());

        if ($piggyBank->target_amount !== null && Money::of(Money::sum($saved, $amount)) > Money::of($piggyBank->target_amount)) {
            throw ValidationException::withMessages(['amount' => __('This is more than is left to save.')]);
        }

        $reserved = (string) DB::table('account_piggy_bank')->where('account_id', $account->id)->sum('current_amount');
        $balance = (string) (Account::query()->withBalance()->findOrFail($account->id)->balance ?? '0');

        if (Money::of(Money::sum($reserved, $amount)) > Money::of($balance)) {
            throw ValidationException::withMessages(['amount' => __('The account does not have enough money that is not already set aside.')]);
        }
    }

    private function load(PiggyBank $piggyBank): PiggyBank
    {
        return $piggyBank->load(['currency', 'objectGroup', 'accounts', 'events' => fn ($query) => $query->with('account')->latest('date')->latest('id')->limit(50)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Ledger $ledger, ?PiggyBank $piggyBank = null): array
    {
        $required = $piggyBank ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255', Rule::unique('piggy_banks')->where('ledger_id', $ledger->id)->ignore($piggyBank?->id)],
            'currency_id' => [$required, Rule::exists('currencies', 'id')],
            'target_amount' => ['nullable', Amount::positive()],
            'start_date' => ['nullable', 'date'],
            'target_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'active' => ['sometimes', 'boolean'],
            'order' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:65000'],
            'object_group' => ['nullable', 'string', 'max:255'],
            'account_ids' => [$required, 'array', 'min:1'],
            'account_ids.*' => ['ulid', Rule::exists('accounts', 'id')
                ->where('ledger_id', $ledger->id)
                ->where('type', AccountType::Asset->value)
                ->whereNull('deleted_at')],
        ]);
    }
}
