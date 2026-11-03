<?php

namespace App\Console\Commands;

use App\Actions\Recurrences\CreateRecurringTransaction;
use App\Models\Recurrence;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('recurring:create')]
#[Description('Create the transactions of recurring transactions that are due')]
class CreateRecurringTransactions extends Command
{
    public function handle(CreateRecurringTransaction $create): void
    {
        $today = CarbonImmutable::today();

        Recurrence::query()
            ->where('active', true)
            ->whereDate('first_date', '<=', $today)
            ->with('repetitions')
            ->each(function (Recurrence $recurrence) use ($create, $today) {
                $count = count($create->createDue($recurrence, $today));

                if ($count > 0) {
                    $this->info("Created {$count} for \"{$recurrence->title}\".");
                }
            });
    }
}
