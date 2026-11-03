<?php

namespace App\Console\Commands;

use App\Enums\TransactionType;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Notifications\WeeklySummaryNotification;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

#[Signature('notifications:weekly')]
#[Description('Send each ledger member a summary of the past week')]
class SendWeeklySummaries extends Command
{
    public function handle(): void
    {
        $end = CarbonImmutable::yesterday();
        $start = $end->subDays(6);

        Ledger::query()->with('members')->each(function (Ledger $ledger) use ($start, $end) {
            Notification::send($ledger->members, new WeeklySummaryNotification(
                $ledger,
                $start,
                $this->totals($ledger, TransactionType::Withdrawal, $start, $end),
                $this->totals($ledger, TransactionType::Deposit, $start, $end),
            ));
        });
    }

    /**
     * @return list<array{currency: string, amount: string}>
     */
    private function totals(Ledger $ledger, TransactionType $type, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = Transaction::query()
            ->whereBelongsTo($ledger)
            ->where('transactions.type', $type)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->join('currencies', 'currencies.id', '=', 'transactions.currency_id')
            ->groupBy('currencies.code', 'currencies.decimal_places')
            ->select('currencies.code', 'currencies.decimal_places', DB::raw('SUM(amount) AS total'))
            ->toBase()
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            /** @var object{code: string, decimal_places: int, total: string} $row */
            $totals[] = ['currency' => $row->code, 'amount' => Money::round($row->total, $row->decimal_places)];
        }

        return $totals;
    }
}
