<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Notifications\BillReminderNotification;
use App\Support\NotifyOnce;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:bills')]
#[Description('Remind ledger members of bills that are due soon or overdue')]
class SendBillReminders extends Command
{
    public function handle(): void
    {
        $today = CarbonImmutable::today();
        $ahead = config()->integer('ledgerfly.notifications.bill_reminder_days');

        Bill::query()->where('active', true)->with('ledger', 'currency')->each(function (Bill $bill) use ($today, $ahead) {
            $members = $bill->ledger->members()->get();

            foreach ($bill->expectedDates($today->subWeek(), $today->addDays($ahead)) as $date) {
                if ($this->isPaid($bill, $date)) {
                    continue;
                }

                $overdue = $date->lessThan($today);
                $key = ($overdue ? 'bill_overdue' : 'bill_due').":{$bill->id}:{$date->toDateString()}";

                NotifyOnce::send($members, $key, new BillReminderNotification($bill, $date, $overdue));
            }
        });
    }

    /**
     * A payment counts for an expected date when it was made after the previous expected date.
     */
    private function isPaid(Bill $bill, CarbonImmutable $date): bool
    {
        $previous = collect($bill->expectedDates($date->subYear(), $date->subDay()))->last();
        $from = $previous?->addDay() ?? $date->subMonth();

        return $bill->transactions()->whereDate('date', '>=', $from)->exists();
    }
}
