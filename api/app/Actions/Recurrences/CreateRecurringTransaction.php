<?php

namespace App\Actions\Recurrences;

use App\Actions\Transactions\SaveTransactionGroup;
use App\Models\Recurrence;
use App\Models\RecurrenceTransaction;
use App\Models\TransactionGroup;
use Carbon\CarbonImmutable;

class CreateRecurringTransaction
{
    public function __construct(
        private SaveTransactionGroup $saveGroup,
        private RecurrenceSchedule $schedule,
    ) {}

    /**
     * Creates every occurrence that is due up to $today and not created yet.
     *
     * @return list<TransactionGroup>
     */
    public function createDue(Recurrence $recurrence, CarbonImmutable $today): array
    {
        if (! $recurrence->active) {
            return [];
        }

        $from = $recurrence->latest_date?->addDay() ?? $recurrence->first_date;
        $created = [];

        foreach ($this->schedule->between($recurrence, $from, $today) as $date) {
            $group = $this->createFor($recurrence, $date);

            if ($group !== null) {
                $created[] = $group;
            }
        }

        return $created;
    }

    public function createFor(Recurrence $recurrence, CarbonImmutable $date): ?TransactionGroup
    {
        if ($recurrence->groups()->whereDate('recurrence_date', $date)->exists()) {
            return null;
        }

        $recurrence->loadMissing('transactions.sourceAccount', 'transactions.destinationAccount', 'creator', 'ledger');

        /** @var list<array<string, mixed>> $splits */
        $splits = $recurrence->transactions->map(fn (RecurrenceTransaction $item) => [
            'type' => $recurrence->type->value,
            'date' => $date->toDateString(),
            'description' => $item->description,
            'amount' => $item->amount,
            'foreign_amount' => $item->foreign_amount,
            'foreign_currency_id' => $item->getAttribute('foreign_currency_id'),
            'source_id' => $item->getAttribute('source_account_id'),
            'destination_id' => $item->getAttribute('destination_account_id'),
            'category_id' => $item->getAttribute('category_id'),
            'budget_id' => $item->getAttribute('budget_id'),
            'bill_id' => $item->getAttribute('bill_id'),
            'tags' => $item->tags ?? [],
            'notes' => $item->getAttribute('notes'),
        ])->values()->all();

        $group = $this->saveGroup->handle(
            $recurrence->ledger,
            $recurrence->creator,
            ['title' => $recurrence->transactions->count() > 1 ? $recurrence->title : null, 'transactions' => $splits],
            groupAttributes: ['recurrence_id' => $recurrence->id, 'recurrence_date' => $date->toDateString()],
        );

        $recurrence->update([
            'occurrences_created' => $recurrence->occurrences_created + 1,
            'latest_date' => $recurrence->latest_date === null || $date->greaterThan($recurrence->latest_date) ? $date : $recurrence->latest_date,
        ]);

        return $group;
    }
}
