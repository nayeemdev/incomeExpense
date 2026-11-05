<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\AssetRole;
use App\Enums\InvestmentKind;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\Ledger;
use App\Support\CurrencyConverter;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Estimates zakat on what the ledger holds: cash, investments and money others owe, less debts due.
 * The home lived in (property) is left out, and the nisab comes from the gold price the member enters.
 */
class ZakatController extends Controller
{
    public function __construct(private CurrencyConverter $converter) {}

    public function __invoke(Request $request, Ledger $ledger): JsonResponse
    {
        $validated = $request->validate([
            'gold_price' => ['required', 'numeric', 'gt:0'],
            'date' => ['nullable', 'date'],
        ]);
        $date = CarbonImmutable::parse($validated['date'] ?? CarbonImmutable::today()->toDateString());
        $primary = Currency::query()->findOrFail($ledger->primary_currency_id);
        $complete = true;
        $items = [];

        $convert = function (string $amount, ?string $currencyId) use ($primary, $date, $ledger, &$complete): string {
            $converted = $currencyId === null ? null : $this->converter->convert($amount, $currencyId, $primary->id, $date, $ledger->id);
            $complete = $complete && $converted !== null;

            return $converted ?? '0';
        };

        $accounts = $ledger->accounts()
            ->whereIn('type', [AccountType::Asset, AccountType::Liability])
            ->where('include_net_worth', true)
            ->where(fn (Builder $query) => $query->whereNull('asset_role')->orWhere('asset_role', '!=', AssetRole::Investment))
            ->withBalance($date)
            ->get();

        foreach ($accounts as $account) {
            /** @var Account $account */
            $balance = $convert((string) ($account->getAttribute('balance') ?? '0'), $account->currency_id);

            if (Money::isZero(Money::round($balance, $primary->decimal_places))) {
                continue;
            }

            $group = match (true) {
                $account->type === AccountType::Asset => 'cash',
                Money::isNegative($balance) => 'debts',
                default => 'receivables',
            };
            $items[] = ['group' => $group, 'name' => $account->name, 'amount' => Money::round($balance, $primary->decimal_places)];
        }

        $investments = $ledger->investments()->whereNull('closed_at')->whereHas('account')->with('account.currency', 'events')->get();

        foreach ($investments as $investment) {
            /** @var Investment $investment */
            if ($investment->kind === InvestmentKind::Property) {
                continue;
            }

            $value = $convert($investment->position($date)->value(), $investment->currency()->id);
            $items[] = [
                'group' => $investment->kind === InvestmentKind::Gold ? 'gold' : 'investments',
                'name' => $investment->account->name,
                'amount' => Money::round($value, $primary->decimal_places),
            ];
        }

        $total = Money::sum('0', ...array_column($items, 'amount'));
        $nisab = Money::of((string) $validated['gold_price'])->mul(Money::of((string) config('ledgerfly.zakat.nisab_gold_grams')), Money::SCALE)->value;
        $due = ! Money::isNegative($total) && Money::of($total) >= Money::of($nisab);
        $rate = (string) config('ledgerfly.zakat.rate');

        return response()->json(['data' => [
            'date' => $date->toDateString(),
            'currency' => $primary->code,
            'complete' => $complete,
            'items' => $items,
            'total' => Money::round($total, $primary->decimal_places),
            'nisab' => Money::round($nisab, $primary->decimal_places),
            'nisab_gold_grams' => (float) config('ledgerfly.zakat.nisab_gold_grams'),
            'rate' => (float) $rate,
            'due' => $due,
            'zakat' => Money::round($due ? Money::of($total)->mul(Money::of($rate), Money::SCALE)->div(100, Money::SCALE)->value : '0', $primary->decimal_places),
        ]]);
    }
}
