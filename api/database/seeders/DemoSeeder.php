<?php

namespace Database\Seeders;

use App\Actions\Accounts\SyncOpeningBalance;
use App\Actions\Budgets\CreateAutoBudgetLimit;
use App\Actions\Recurrences\CreateRecurringTransaction;
use App\Actions\Transactions\SaveTransactionGroup;
use App\Enums\AccountType;
use App\Enums\LedgerRole;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Ledger;
use App\Models\LinkType;
use App\Models\TransactionGroup;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A year of realistic activity for trying out every feature locally. Never run by the
 * default seeder; run it with: php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    private Ledger $ledger;

    private User $owner;

    /** @var array<string, Account> */
    private array $accounts = [];

    private string $scale = '1';

    public function __construct(
        private SaveTransactionGroup $saveGroup,
        private SyncOpeningBalance $openingBalance,
        private CreateAutoBudgetLimit $autoBudget,
        private CreateRecurringTransaction $recurring,
    ) {}

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('The demo seeder does not run in production.');

            return;
        }

        if (User::query()->where('email', config()->string('ledgerfly.demo.email'))->exists()) {
            $this->command?->error('The demo user already exists. Run it on a fresh database (php artisan migrate:fresh --seed) or set DEMO_EMAIL.');

            return;
        }

        $this->call([CurrencySeeder::class, LinkTypeSeeder::class]);
        mt_srand(2026);
        $this->scale = (string) config('ledgerfly.demo.scale', '1');

        DB::transaction(function () {
            $configured = config('ledgerfly.demo.password');
            $password = is_string($configured) && $configured !== '' ? $configured : Str::password(16, symbols: false);
            $this->createPeople($password);
            $this->createAccounts();
            $this->createPlanning();
            $this->createHistory();
            $this->createRecurring();
            $this->createRules();
            $this->createExtras();

            $this->command?->info('Demo ledger ready. Sign in as '.$this->owner->email.($configured ? ' with DEMO_PASSWORD from .env' : ' with password '.$password));
        });
    }

    private function createPeople(string $password): void
    {
        $email = config()->string('ledgerfly.demo.email');

        $this->owner = User::create(['name' => 'Nathan Drake', 'email' => $email, 'password' => $password, 'is_admin' => User::query()->doesntExist()]);
        $partner = User::create(['name' => 'Elena Fisher', 'email' => 'elena@'.Str::after($email, '@'), 'password' => $password]);

        $currency = Currency::findByCode(config()->string('ledgerfly.demo.currency')) ?? Currency::findByCode('USD');
        $this->ledger = Ledger::create(['name' => 'Household', 'primary_currency_id' => $currency?->id]);
        $this->ledger->addMember($this->owner, LedgerRole::Owner);
        $this->ledger->addMember($partner, LedgerRole::Editor);
    }

    private function createAccounts(): void
    {
        $primary = $this->ledger->primary_currency_id;
        $euro = Currency::findByCode('EUR')?->id;
        $start = CarbonImmutable::today()->subYear()->startOfMonth();

        $definitions = [
            'checking' => ['Everyday Checking', AccountType::Asset, ['asset_role' => 'default', 'low_balance_threshold' => $this->money('500')], '3200', $primary],
            'savings' => ['Rainy Day Savings', AccountType::Asset, ['asset_role' => 'savings'], '8500', $primary],
            'wallet' => ['Wallet', AccountType::Asset, ['asset_role' => 'cash_wallet'], '120', $primary],
            'card' => ['Visa Card', AccountType::Asset, ['asset_role' => 'credit_card', 'credit_card_payment_day' => 25], '-350', $primary],
            'euro' => ['Travel Euros', AccountType::Asset, ['asset_role' => 'default'], '400', $euro],
            'loan' => ['Car Loan', AccountType::Liability, ['liability_type' => 'loan', 'liability_direction' => 'owed', 'interest' => '4.9', 'interest_period' => 'yearly'], '12000', $primary],
            'lent' => ['Loan to Sam', AccountType::Liability, ['liability_type' => 'debt', 'liability_direction' => 'lent'], '0', $primary],
        ];

        foreach ($definitions as $key => [$name, $type, $extra, $opening, $currencyId]) {
            $account = $this->ledger->accounts()->create(['type' => $type, 'name' => $name, 'currency_id' => $currencyId, ...$extra]);
            $this->openingBalance->handle($account, $this->owner, $key === 'euro' ? $opening : $this->money($opening), $start->toDateString());
            $this->accounts[$key] = $account;
        }

        $this->ledger->members()->updateExistingPivot($this->owner->id, ['default_account_id' => $this->accounts['card']->id]);

        if ($euro !== null && $euro !== $primary) {
            for ($month = 12; $month >= 0; $month--) {
                ExchangeRate::create([
                    'from_currency_id' => $primary,
                    'to_currency_id' => $euro,
                    'date' => CarbonImmutable::today()->subMonths($month)->startOfMonth()->toDateString(),
                    'rate' => number_format((0.9 + mt_rand(-30, 30) / 1000) / (float) $this->scale, 6, '.', ''),
                ]);
            }
        }
    }

    private function createPlanning(): void
    {
        $primary = $this->ledger->primary_currency_id;
        $today = CarbonImmutable::today();

        foreach (['Groceries' => '700', 'Eating out' => '250', 'Transport' => '180', 'Fun' => '150'] as $name => $amount) {
            $budget = $this->ledger->budgets()->create([
                'name' => $name,
                'auto_budget_type' => $name === 'Fun' ? 'rollover' : 'reset',
                'auto_budget_currency_id' => $primary,
                'auto_budget_amount' => $this->money($amount),
                'auto_budget_period' => 'monthly',
            ]);

            for ($month = 11; $month >= 0; $month--) {
                $this->autoBudget->handle($budget, $today->subMonths($month));
            }
        }

        for ($month = 11; $month >= 0; $month--) {
            $date = $today->subMonths($month);
            $this->ledger->availableBudgets()->create([
                'currency_id' => $primary,
                'start_date' => $date->startOfMonth()->toDateString(),
                'end_date' => $date->endOfMonth()->toDateString(),
                'amount' => $this->money('4200'),
            ]);
        }

        $bills = [
            ['Rent', '950', '950', 1, 'monthly', 'Home'],
            ['Electricity', '60', '110', 12, 'monthly', 'Home'],
            ['Internet', '45', '45', 18, 'monthly', 'Home'],
            ['Phone plan', '25', '30', 20, 'monthly', 'Subscriptions'],
            ['Streaming', '15.99', '15.99', 8, 'monthly', 'Subscriptions'],
            ['Car insurance', '480', '520', 3, 'half_year', 'Car'],
        ];

        foreach ($bills as [$name, $min, $max, $day, $frequency, $group]) {
            $this->ledger->bills()->create([
                'name' => $name,
                'currency_id' => $primary,
                'amount_min' => $this->money($min),
                'amount_max' => $this->money($max),
                'date' => $today->subYear()->startOfMonth()->setDay($day)->toDateString(),
                'repeat_freq' => $frequency,
                'object_group_id' => $this->ledger->objectGroups()->firstOrCreate(['title' => $group])->id,
            ]);
        }

        $holiday = $this->ledger->piggyBanks()->create([
            'name' => 'Summer holiday', 'currency_id' => $primary, 'target_amount' => $this->money('2500'),
            'target_date' => $today->addMonths(5)->toDateString(),
        ]);
        $laptop = $this->ledger->piggyBanks()->create(['name' => 'New laptop', 'currency_id' => $primary, 'target_amount' => $this->money('1600')]);

        foreach ([[$holiday, $this->money('1150')], [$laptop, $this->money('640')]] as [$piggyBank, $saved]) {
            $piggyBank->accounts()->attach($this->accounts['savings']->id, ['current_amount' => $saved]);
            $piggyBank->events()->create(['account_id' => $this->accounts['savings']->id, 'amount' => $saved, 'date' => $today->subMonths(2)->toDateString()]);
        }
    }

    private function createHistory(): void
    {
        $budgets = $this->ledger->budgets()->pluck('id', 'name');
        /** @var array<string, Bill> $bills */
        $bills = [];

        foreach ($this->ledger->bills()->get() as $bill) {
            $bills[$bill->name] = $bill;
        }
        $today = CarbonImmutable::today();

        for ($date = $today->subYear()->startOfMonth(); $date->lessThanOrEqualTo($today); $date = $date->addDay()) {
            if ($date->day === 1) {
                $this->deposit($date, 'Salary', (string) (4100 + mt_rand(0, 4) * 50), 'Acme Corp', 'Salary', 'checking');
                $this->spend($date, 'Rent', '950', 'Landlord', 'Housing', 'checking', bill: $bills['Rent']->id);
                $this->transfer($date, 'Monthly saving', '400', 'checking', 'savings');
            }

            if ($date->day === 5) {
                $this->transfer($date, 'Car loan payment', '310', 'checking', 'loan', 'Car');
            }

            if (in_array($date->day, [6, 20], true)) {
                $this->spend($date, 'Household supplies', (string) mt_rand(15, 45), 'HomeGoods', 'Household', 'card', ['household']);
            }

            if ($date->day === 8 && mt_rand(1, 2) === 1) {
                $this->spend($date, collect(['New shirt', 'Shoes', 'Jeans'])->random(), (string) mt_rand(40, 150), 'Urban Outfit', 'Shopping', 'card');
            }

            if ($date->day === 25) {
                $this->transfer($date, 'Credit card payment', (string) mt_rand(500, 900), 'checking', 'card');
            }

            foreach (['Electricity' => 12, 'Internet' => 18, 'Phone plan' => 20, 'Streaming' => 8] as $name => $day) {
                if ($date->day === $day) {
                    $bill = $bills[$name];
                    $amount = bcdiv(number_format(mt_rand((int) ((float) $bill->amount_min * 100), (int) ((float) $bill->amount_max * 100)) / 100, 2, '.', ''), $this->scale, 2);
                    $this->spend($date, $name, $amount, $name === 'Streaming' ? 'StreamCo' : "{$name} provider", $name === 'Streaming' ? 'Subscriptions' : 'Utilities', 'checking', bill: $bill->id);
                }
            }

            if (in_array($date->month, [3, 9], true) && $date->day === 3) {
                $this->spend($date, 'Car insurance', (string) mt_rand(480, 520), 'SafeDrive Insurance', 'Car', 'checking', bill: $bills['Car insurance']->id);
            }

            if ($date->isSaturday()) {
                $this->spend($date, 'Weekly groceries', (string) mt_rand(85, 160), mt_rand(0, 1) ? 'FreshMart' : 'Corner Grocer', 'Groceries', 'card', ['household'], $budgets['Groceries']);
            }

            if ($date->isWeekday() && mt_rand(1, 10) <= 6) {
                $this->spend($date, 'Coffee', number_format(mt_rand(320, 520) / 100, 2), 'Bean There Cafe', 'Eating out', mt_rand(1, 4) === 1 ? 'wallet' : 'card', [], $budgets['Eating out']);
            }

            if ($date->isWeekday() && mt_rand(1, 10) <= 4) {
                $this->spend($date, 'Metro ride', '2.75', 'City Transit', 'Transport', 'card', ['commute'], $budgets['Transport']);
            }

            if ($date->isWeekday() && mt_rand(1, 10) <= 3) {
                $this->spend($date, 'Lunch', (string) mt_rand(8, 15), collect(['Noodle Bar', 'Deli Corner', 'Spice Route'])->random(), 'Eating out', 'card', [], $budgets['Eating out']);
            }

            if (($date->isTuesday() || $date->isThursday()) && mt_rand(1, 3) === 1) {
                $this->spend($date, 'Ride home', (string) mt_rand(8, 18), 'QuickRide', 'Transport', 'card', [], $budgets['Transport']);
            }

            if ($date->isWednesday()) {
                $this->spend($date, 'Grocery top-up', (string) mt_rand(20, 45), 'Corner Grocer', 'Groceries', 'card', ['household'], $budgets['Groceries']);
            }

            if ($date->isWeekday() && mt_rand(1, 12) === 1) {
                $this->spend($date, 'Pharmacy', (string) mt_rand(8, 40), 'Wellness Pharmacy', 'Health', 'card');
            }

            if ($date->isFriday() && mt_rand(1, 3) === 1) {
                $this->spend($date, 'Dinner out', (string) mt_rand(35, 90), collect(['Luigi\'s', 'Spice Route', 'Noodle Bar'])->random(), 'Eating out', 'card', ['weekend'], $budgets['Eating out']);
            }

            if ($date->isSunday() && mt_rand(1, 4) === 1) {
                $this->spend($date, 'Cinema', (string) mt_rand(18, 32), 'Starlight Cinema', 'Entertainment', 'card', ['weekend'], $budgets['Fun']);
            }

            if ($date->day === 15 && mt_rand(1, 2) === 1) {
                $this->spend($date, 'Fuel', (string) mt_rand(45, 70), 'QuickFuel', 'Car', 'card', [], $budgets['Transport']);
            }

            if ($date->day === 10 && $date->month % 3 === 0) {
                $this->deposit($date, 'Freelance project', (string) mt_rand(600, 1400), 'Pixel Studio', 'Side income', 'checking');
            }
        }

        $trip = $today->subMonths(4)->startOfMonth()->addDays(9);
        $this->spend($trip, 'Museum tickets', '32', 'City Museum', 'Travel', 'euro', ['trip']);
        $this->spend($trip->addDay(), 'Dinner in Lisbon', '58', 'Tasca do Bairro', 'Travel', 'euro', ['trip']);

        $this->saveGroup->handle($this->ledger, $this->owner, [
            'title' => 'Big shop',
            'transactions' => [
                $this->split($today->subDays(6), 'Groceries', '86.40', 'card', 'MegaStore', 'Groceries', $budgets['Groceries']),
                $this->split($today->subDays(6), 'Kitchen towels and soap', '23.15', 'card', 'MegaStore', 'Household'),
                $this->split($today->subDays(6), 'Birthday gift', '39.99', 'card', 'MegaStore', 'Gifts'),
            ],
        ], applyRules: false, notify: false);

        $this->save([
            'type' => 'withdrawal', 'date' => $today->subMonths(2)->toDateString(), 'description' => 'Lent to Sam for rent',
            'amount' => $this->money('300'), 'source_id' => $this->accounts['checking']->id, 'destination_id' => $this->accounts['lent']->id,
        ]);
        $this->save([
            'type' => 'deposit', 'date' => $today->subMonth()->toDateString(), 'description' => 'Sam paid back part',
            'amount' => $this->money('100'), 'source_id' => $this->accounts['lent']->id, 'destination_id' => $this->accounts['checking']->id,
        ]);

        $headphones = $this->spend($today->subDays(20), 'Headphones', '129.00', 'Gadget Hub', 'Electronics', 'card');
        $refund = $this->deposit($today->subDays(12), 'Headphones refund', '129.00', 'Gadget Hub', 'Electronics', 'card');
        $refundType = LinkType::query()->whereNull('ledger_id')->where('name', 'Refund')->value('id');

        if ($refundType !== null) {
            $this->ledger->transactionLinks()->create(['link_type_id' => $refundType, 'outward_group_id' => $refund->id, 'inward_group_id' => $headphones->id]);
        }
    }

    private function createRecurring(): void
    {
        $recurrence = $this->ledger->recurrences()->create([
            'created_by' => $this->owner->id,
            'type' => 'withdrawal',
            'title' => 'Gym membership',
            'first_date' => CarbonImmutable::today()->subMonths(3)->startOfMonth()->addDays(4)->toDateString(),
            'apply_rules' => false,
        ]);
        $recurrence->repetitions()->create(['type' => 'monthly', 'moment' => '5', 'skip' => 0, 'weekend' => 'after']);
        $recurrence->transactions()->create([
            'description' => 'Gym membership',
            'amount' => $this->money('39'),
            'source_account_id' => $this->accounts['card']->id,
            'destination_account_id' => $this->ledger->accounts()->firstOrCreate(['type' => AccountType::Expense, 'name' => 'FitLife Gym'])->id,
            'category_id' => $this->ledger->categories()->firstOrCreate(['name' => 'Health'])->id,
        ]);

        $this->recurring->createDue($recurrence->refresh()->load('repetitions'), CarbonImmutable::today());
    }

    private function createRules(): void
    {
        $group = $this->ledger->ruleGroups()->create(['title' => 'Everyday']);

        $rule = $this->ledger->rules()->create(['rule_group_id' => $group->id, 'title' => 'Ride sharing is transport']);
        $rule->triggers()->create(['type' => 'description_contains', 'value' => 'ride', 'order' => 0]);
        $rule->actions()->create(['type' => 'set_category', 'value' => 'Transport', 'order' => 0]);

        $rule = $this->ledger->rules()->create(['rule_group_id' => $group->id, 'title' => 'Tag big purchases', 'order' => 1]);
        $rule->triggers()->create(['type' => 'amount_more', 'value' => $this->money('250'), 'order' => 0]);
        $rule->triggers()->create(['type' => 'transaction_type', 'value' => 'withdrawal', 'order' => 1]);
        $rule->actions()->create(['type' => 'add_tag', 'value' => 'big purchase', 'order' => 0]);
    }

    private function createExtras(): void
    {
        DB::table('saved_searches')->insert([
            ['ledger_id' => $this->ledger->id, 'user_id' => $this->owner->id, 'name' => 'Weekend fun', 'query' => 'tag:weekend', 'created_at' => now(), 'updated_at' => now()],
            ['ledger_id' => $this->ledger->id, 'user_id' => $this->owner->id, 'name' => 'Big card spending', 'query' => 'account:Visa amount_more:'.$this->money('100').' type:withdrawal', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $preferences = $this->owner->preferences ?? [];
        $preferences['large_transaction_amount'] = (int) $this->money('500');
        $this->owner->update(['preferences' => $preferences]);
    }

    /**
     * @param  list<string>  $tags
     */
    private function spend(CarbonImmutable $date, string $description, string $amount, string $payee, string $category, string $from, array $tags = [], ?int $budget = null, ?int $bill = null): TransactionGroup
    {
        return $this->save([
            'type' => 'withdrawal', 'date' => $date->toDateString(), 'description' => $description, 'amount' => $this->amountIn($amount, $from),
            'source_id' => $this->accounts[$from]->id, 'destination_name' => $payee, 'category_name' => $category,
            'tags' => $tags, 'budget_id' => $budget, 'bill_id' => $bill,
        ]);
    }

    private function deposit(CarbonImmutable $date, string $description, string $amount, string $payer, string $category, string $to): TransactionGroup
    {
        return $this->save([
            'type' => 'deposit', 'date' => $date->toDateString(), 'description' => $description, 'amount' => $this->amountIn($amount, $to),
            'source_name' => $payer, 'destination_id' => $this->accounts[$to]->id, 'category_name' => $category,
        ]);
    }

    private function transfer(CarbonImmutable $date, string $description, string $amount, string $from, string $to, ?string $category = null): TransactionGroup
    {
        $type = $this->accounts[$to]->type === AccountType::Liability ? 'withdrawal' : 'transfer';

        return $this->save([
            'type' => $type, 'date' => $date->toDateString(), 'description' => $description, 'amount' => $this->amountIn($amount, $from),
            'source_id' => $this->accounts[$from]->id, 'destination_id' => $this->accounts[$to]->id, 'category_name' => $category,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function split(CarbonImmutable $date, string $description, string $amount, string $from, string $payee, string $category, ?int $budget = null): array
    {
        return [
            'type' => 'withdrawal', 'date' => $date->toDateString(), 'description' => $description, 'amount' => $this->amountIn($amount, $from),
            'source_id' => $this->accounts[$from]->id, 'destination_name' => $payee, 'category_name' => $category, 'budget_id' => $budget,
        ];
    }

    private function money(string $amount): string
    {
        return bcmul($amount, $this->scale, 2);
    }

    /** Amounts on the euro travel account stay in euros; everything else is in the ledger currency. */
    private function amountIn(string $amount, string $account): string
    {
        return $account === 'euro' ? $amount : $this->money($amount);
    }

    /**
     * @param  array<string, mixed>  $split
     */
    private function save(array $split): TransactionGroup
    {
        return $this->saveGroup->handle($this->ledger, $this->owner, ['transactions' => [$split]], applyRules: false, notify: false);
    }
}
