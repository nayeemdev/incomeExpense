<?php

namespace App\Http\Resources;

use App\Models\Account;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transaction */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->transaction_group_id,
            'group_title' => $this->whenLoaded('group', fn () => $this->group->title),
            'type' => $this->type,
            'date' => $this->date->toDateString(),
            'description' => $this->description,
            'source' => $this->account($this->sourceAccount),
            'destination' => $this->account($this->destinationAccount),
            'currency' => new CurrencyResource($this->currency),
            'amount' => Money::round($this->amount, $this->currency->decimal_places),
            'foreign_currency' => $this->foreignCurrency ? new CurrencyResource($this->foreignCurrency) : null,
            'foreign_amount' => $this->foreignCurrency && $this->foreign_amount !== null
                ? Money::round($this->foreign_amount, $this->foreignCurrency->decimal_places)
                : null,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'icon' => $this->category->icon,
                'color' => $this->category->color,
            ] : null),
            'budget' => $this->whenLoaded('budget', fn () => $this->budget ? ['id' => $this->budget->id, 'name' => $this->budget->name] : null),
            'bill' => $this->whenLoaded('bill', fn () => $this->bill ? ['id' => $this->bill->id, 'name' => $this->bill->name] : null),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])),
            'notes' => $this->notes,
            'running_balance' => $this->when(array_key_exists('running_balance', $this->resource->getAttributes()), fn () => $this->runningBalance($request)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function account(Account $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'type' => $account->type,
        ];
    }

    private function runningBalance(Request $request): string
    {
        $accountId = $request->string('account_id')->toString();
        $account = $this->source_account_id === $accountId ? $this->sourceAccount : $this->destinationAccount;

        return Money::round((string) $this->running_balance, $account->currency->decimal_places ?? Money::SCALE);
    }
}
