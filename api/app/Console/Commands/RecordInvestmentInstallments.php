<?php

namespace App\Console\Commands;

use App\Actions\Investments\RecordInvestmentEvent;
use App\Enums\InvestmentEventType;
use App\Enums\LedgerRole;
use App\Models\Investment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

#[Signature('investments:installments')]
#[Description('Record the installments of investments paid in regularly, such as a DPS')]
class RecordInvestmentInstallments extends Command
{
    public function handle(RecordInvestmentEvent $recorder): void
    {
        $today = CarbonImmutable::today();

        Investment::query()
            ->whereNull('closed_at')
            ->whereNotNull('contribution_amount')
            ->whereNotNull('contribution_account_id')
            ->whereDate('next_contribution_date', '<=', $today)
            ->whereHas('account')
            ->with('ledger', 'account.currency', 'events')
            ->each(function (Investment $investment) use ($recorder, $today) {
                /** @var User|null $owner */
                $owner = $investment->ledger->members()->wherePivot('role', LedgerRole::Owner->value)->first();

                foreach ($investment->contributionDates($today) as $date) {
                    try {
                        DB::transaction(function () use ($recorder, $investment, $owner, $date) {
                            $recorder->handle($investment->refresh(), $owner ?? throw new LogicException('A ledger always has an owner.'), [
                                'type' => InvestmentEventType::Buy->value,
                                'date' => $date->toDateString(),
                                'amount' => (string) $investment->contribution_amount,
                                'account_id' => $investment->contribution_account_id,
                            ]);
                            $investment->update(['next_contribution_date' => $investment->contribution_frequency?->add($date)]);
                        });
                    } catch (ValidationException $exception) {
                        $this->warn("Skipped {$investment->id}: {$exception->getMessage()}");

                        return;
                    }
                }
            });
    }
}
