<?php

namespace App\Http\Resources;

use App\Models\Account;
use App\Models\PiggyBank;
use App\Models\PiggyBankEvent;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PiggyBank */
class PiggyBankResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $decimals = $this->currency->decimal_places;
        $saved = Money::sum(...$this->accounts->map(fn (Account $account) => (string) $account->getRelation('pivot')->getAttribute('current_amount'))->all());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'object_group' => $this->objectGroup?->title,
            'currency' => new CurrencyResource($this->currency),
            'target_amount' => $this->target_amount !== null ? Money::round($this->target_amount, $decimals) : null,
            'saved_amount' => Money::round($saved, $decimals),
            'left_to_save' => $this->target_amount !== null
                ? Money::round(Money::of($this->target_amount)->sub(Money::of($saved))->value, $decimals)
                : null,
            'start_date' => $this->start_date?->toDateString(),
            'target_date' => $this->target_date?->toDateString(),
            'active' => $this->active,
            'order' => $this->order,
            'notes' => $this->notes,
            'accounts' => $this->accounts->map(fn (Account $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'current_amount' => Money::round((string) $account->getRelation('pivot')->getAttribute('current_amount'), $decimals),
            ]),
            'events' => $this->whenLoaded('events', fn () => $this->events->map(fn (PiggyBankEvent $event) => [
                'id' => $event->id,
                'account' => ['id' => $event->account->id, 'name' => $event->account->name],
                'amount' => Money::round($event->amount, $decimals),
                'date' => $event->date->toDateString(),
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
