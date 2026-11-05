<?php

namespace App\Http\Controllers;

use App\Actions\Accounts\DeleteAccount;
use App\Actions\Investments\RecordInvestmentEvent;
use App\Enums\AccountType;
use App\Enums\AssetRole;
use App\Enums\InvestmentEventType;
use App\Enums\InvestmentKind;
use App\Enums\RepeatFrequency;
use App\Http\Resources\InvestmentResource;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentEvent;
use App\Models\Ledger;
use App\Models\User;
use App\Rules\Amount;
use App\Support\CurrencyConverter;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;

class InvestmentController extends Controller
{
    public function __construct(
        private RecordInvestmentEvent $recorder,
        private DeleteAccount $deleteAccount,
        private CurrencyConverter $converter,
    ) {}

    public function index(Request $request, Ledger $ledger): AnonymousResourceCollection
    {
        $request->validate(['status' => ['nullable', Rule::in(['open', 'closed'])]]);

        $investments = $ledger->investments()
            ->with('account.currency', 'events')
            ->whereHas('account')
            ->when($request->input('status') === 'open', fn (Builder $query) => $query->whereNull('closed_at'))
            ->when($request->input('status') === 'closed', fn (Builder $query) => $query->whereNotNull('closed_at'))
            ->get()
            ->sortBy(fn (Investment $investment) => mb_strtolower($investment->account->name))
            ->values();

        return InvestmentResource::collection($investments)->additional(['summary' => $this->summary($ledger, $investments)]);
    }

    public function store(Request $request, Ledger $ledger, #[CurrentUser] User $user): JsonResponse
    {
        $validated = $this->validated($request);
        $purchase = $request->validate([
            'purchase' => ['nullable', 'array'],
            'purchase.amount' => ['required_with:purchase', Amount::positive()],
            'purchase.units' => [Rule::requiredIf(fn () => $request->filled('purchase') && InvestmentKind::from($validated['kind'])->tracksUnits()), 'nullable', Amount::positive()],
            'purchase.date' => ['required_with:purchase', 'date'],
            'purchase.account_id' => ['nullable', 'ulid', $this->ownAccountRule($ledger)],
        ])['purchase'] ?? null;

        $investment = DB::transaction(function () use ($ledger, $user, $validated, $purchase) {
            $account = $ledger->accounts()->create([
                'type' => AccountType::Asset,
                'asset_role' => AssetRole::Investment,
                'name' => $validated['name'],
                'currency_id' => $validated['currency_id'],
            ]);

            $investment = $ledger->investments()->create([...collect($validated)->except('name', 'currency_id')->all(), 'account_id' => $account->id]);

            if ($purchase !== null) {
                $this->ensureCurrency($investment, $purchase['account_id'] ?? null, 'purchase.account_id');
                $this->recorder->handle($investment, $user, ['type' => InvestmentEventType::Buy->value, ...$purchase]);
            }

            return $investment;
        });

        return (new InvestmentResource($this->load($investment)))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Ledger $ledger, Investment $investment): InvestmentResource
    {
        return new InvestmentResource($this->load($investment));
    }

    public function update(Request $request, Ledger $ledger, Investment $investment): InvestmentResource
    {
        $validated = $this->validated($request, $investment);

        DB::transaction(function () use ($investment, $validated) {
            $investment->account->update(collect($validated)->only('name')->all());
            $investment->update(collect($validated)->except('name', 'currency_id')->all());
        });

        return new InvestmentResource($this->load($investment));
    }

    /**
     * Removes the investment together with its account and every transaction booked on it.
     */
    public function destroy(Ledger $ledger, Investment $investment): Response
    {
        $this->deleteAccount->handle($investment->account);

        return response()->noContent();
    }

    public function storeEvent(Request $request, Ledger $ledger, Investment $investment, #[CurrentUser] User $user): InvestmentResource
    {
        $type = InvestmentEventType::tryFrom((string) $request->input('type'));
        $units = $investment->kind->tracksUnits();
        $movesMoney = in_array($type, [InvestmentEventType::Sell, InvestmentEventType::Income], true);
        $pricePerUnit = $type === InvestmentEventType::Valuation && $units;

        $validated = $request->validate([
            'type' => ['required', Rule::enum(InvestmentEventType::class)],
            'date' => ['required', 'date'],
            'amount' => [Rule::requiredIf(! $pricePerUnit), 'nullable', Amount::positive()],
            'units' => [Rule::requiredIf($units && in_array($type, [InvestmentEventType::Buy, InvestmentEventType::Sell], true)), 'nullable', Amount::positive()],
            'price' => [Rule::requiredIf($pricePerUnit), 'nullable', Amount::positive()],
            'account_id' => [Rule::requiredIf($movesMoney), 'nullable', 'ulid', $this->ownAccountRule($ledger)],
            'close' => ['sometimes', 'boolean'],
        ]);

        $this->ensureCurrency($investment, $validated['account_id'] ?? null, 'account_id');

        if ($type === InvestmentEventType::Sell && $units) {
            $held = $investment->position(CarbonImmutable::parse($validated['date']))->units;

            if (Money::of((string) $validated['units']) > Money::of($held)) {
                throw ValidationException::withMessages(['units' => __('You hold :units units on that date.', ['units' => rtrim(rtrim(Money::round($held, 6), '0'), '.')])]);
            }
        }

        $this->recorder->handle($investment, $user, $validated);

        return new InvestmentResource($this->load($investment->refresh()));
    }

    public function destroyEvent(Ledger $ledger, Investment $investment, InvestmentEvent $event): InvestmentResource
    {
        $this->recorder->delete($event);

        return new InvestmentResource($this->load($investment->refresh()));
    }

    private function load(Investment $investment): Investment
    {
        return $investment->load('account.currency', 'events');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Investment $investment = null): array
    {
        $required = $investment ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'kind' => [$required, Rule::enum(InvestmentKind::class)],
            'currency_id' => [$investment ? 'prohibited' : 'required', Rule::exists('currencies', 'id')],
            'symbol' => ['nullable', 'string', 'max:32'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'payout_frequency' => ['nullable', Rule::enum(RepeatFrequency::class)],
            'start_date' => ['nullable', 'date', Rule::requiredIf(fn () => $request->filled('payout_frequency'))],
            'maturity_date' => ['nullable', 'date', 'after:start_date'],
            'notes' => ['nullable', 'string', 'max:65000'],
        ]);
    }

    private function ownAccountRule(Ledger $ledger): Exists
    {
        return Rule::exists('accounts', 'id')
            ->where('ledger_id', $ledger->id)
            ->where('type', AccountType::Asset->value)
            ->whereNull('deleted_at');
    }

    private function ensureCurrency(Investment $investment, ?string $accountId, string $key): void
    {
        if ($accountId === null) {
            return;
        }

        if ($accountId === $investment->account_id || $investment->ledger->accounts()->findOrFail($accountId)->currency_id !== $investment->currency()->id) {
            throw ValidationException::withMessages([$key => __('Choose another account in :currency.', ['currency' => $investment->currency()->code])]);
        }
    }

    /**
     * Portfolio totals in the ledger's primary currency, with the share of each kind of investment.
     *
     * @param  Collection<int, Investment>  $investments
     * @return array<string, mixed>
     */
    private function summary(Ledger $ledger, Collection $investments): array
    {
        $primary = Currency::query()->findOrFail($ledger->primary_currency_id);
        $today = CarbonImmutable::today();
        $totals = ['value' => '0', 'cost' => '0', 'gain' => '0', 'income' => '0', 'realized' => '0'];
        $byKind = [];
        $complete = true;

        foreach ($investments as $investment) {
            $position = $investment->position($today);
            $amounts = ['value' => $position->value(), 'cost' => $position->cost, 'gain' => $position->gain(), 'income' => $position->income, 'realized' => $position->realized];

            foreach ($amounts as $key => $amount) {
                $converted = $this->converter->convert($amount, $investment->currency()->id, $primary->id, $today, $ledger->id);

                if ($converted === null) {
                    $complete = false;

                    continue 2;
                }

                $totals[$key] = Money::sum($totals[$key], $converted);

                if ($key === 'value' && $position->isOpen()) {
                    $byKind[$investment->kind->value] = Money::sum($byKind[$investment->kind->value] ?? '0', $converted);
                }
            }
        }

        $round = fn (string $amount) => Money::round($amount, $primary->decimal_places);
        arsort($byKind, SORT_NUMERIC);

        return [
            'currency' => $primary->code,
            'complete' => $complete,
            ...array_map($round, $totals),
            'gain_percent' => Money::isZero($totals['cost']) ? null : Money::round(Money::of($totals['gain'])->mul(100, Money::SCALE)->div(Money::of($totals['cost']), Money::SCALE)->value, 2),
            'allocation' => collect($byKind)->map(fn (string $value, string $kind) => ['kind' => $kind, 'value' => $round($value)])->values(),
        ];
    }
}
