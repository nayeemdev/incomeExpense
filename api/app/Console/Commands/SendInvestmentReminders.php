<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Notifications\InvestmentReminderNotification;
use App\Support\NotifyOnce;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:investments')]
#[Description('Remind ledger members of investment payouts that are due and investments about to mature')]
class SendInvestmentReminders extends Command
{
    public function handle(): void
    {
        $today = CarbonImmutable::today();
        $ahead = config()->integer('ledgerfly.notifications.bill_reminder_days');

        Investment::query()->whereNull('closed_at')->whereHas('account')->with('ledger', 'account.currency', 'events')->each(function (Investment $investment) use ($today, $ahead) {
            $members = $investment->ledger->members()->get();
            $amount = $investment->position($today)->payoutAmount();

            foreach ($investment->pendingPayoutDates($today) as $date) {
                if ($date->greaterThanOrEqualTo($today->subWeek())) {
                    NotifyOnce::send($members, "investment_payout:{$investment->id}:{$date->toDateString()}", new InvestmentReminderNotification($investment, $date, false, $amount));
                }
            }

            $maturity = $investment->maturity_date;

            if ($maturity !== null && $maturity->betweenIncluded($today, $today->addDays($ahead))) {
                NotifyOnce::send($members, "investment_maturing:{$investment->id}", new InvestmentReminderNotification($investment, $maturity, true));
            }
        });
    }
}
