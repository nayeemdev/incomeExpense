<?php

namespace App\Http\Resources;

use App\Models\Recurrence;
use App\Models\RecurrenceRepetition;
use App\Models\RecurrenceTransaction;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Recurrence */
class RecurrenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var RecurrenceRepetition|null $repetition */
        $repetition = $this->repetitions->first();
        $upcoming = $this->resource->getAttribute('upcoming');

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'first_date' => $this->first_date->toDateString(),
            'repeat_until' => $this->repeat_until?->toDateString(),
            'max_occurrences' => $this->max_occurrences,
            'occurrences_created' => $this->occurrences_created,
            'latest_date' => $this->latest_date?->toDateString(),
            'apply_rules' => $this->apply_rules,
            'active' => $this->active,
            'repetition' => $repetition ? [
                'type' => $repetition->type,
                'moment' => $repetition->moment,
                'skip' => $repetition->skip,
                'weekend' => $repetition->weekend,
            ] : null,
            'transactions' => $this->transactions->map(function (RecurrenceTransaction $item) {
                $currency = $item->sourceAccount->currency ?? $item->destinationAccount->currency;

                return [
                    'description' => $item->description,
                    'amount' => $currency ? Money::round($item->amount, $currency->decimal_places) : $item->amount,
                    'currency' => $currency ? new CurrencyResource($currency) : null,
                    'foreign_amount' => $item->foreign_amount,
                    'foreign_currency_id' => $item->getAttribute('foreign_currency_id'),
                    'source' => ['id' => $item->sourceAccount->id, 'name' => $item->sourceAccount->name, 'type' => $item->sourceAccount->type],
                    'destination' => ['id' => $item->destinationAccount->id, 'name' => $item->destinationAccount->name, 'type' => $item->destinationAccount->type],
                    'category' => $item->category?->only('id', 'name'),
                    'budget' => $item->budget?->only('id', 'name'),
                    'bill' => $item->bill?->only('id', 'name'),
                    'tags' => $item->tags ?? [],
                    'notes' => $item->getAttribute('notes'),
                ];
            }),
            'upcoming' => is_array($upcoming) ? array_map(fn (CarbonImmutable $date) => $date->toDateString(), $upcoming) : [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
