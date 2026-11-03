<?php

namespace App\Http\Resources;

use App\Models\Account;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Account */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $decimals = $this->currency->decimal_places ?? Money::SCALE;
        $own = $this->type->isOwn();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'object_group' => $this->whenLoaded('objectGroup', fn () => $this->objectGroup?->title),
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'asset_role' => $this->asset_role,
            'liability_type' => $this->liability_type,
            'liability_direction' => $this->liability_direction,
            'interest' => $this->interest,
            'interest_period' => $this->interest_period,
            'credit_card_payment_day' => $this->credit_card_payment_day,
            'iban' => $this->iban,
            'bic' => $this->bic,
            'account_number' => $this->account_number,
            'virtual_balance' => $own ? Money::round($this->virtual_balance, $decimals) : null,
            'low_balance_threshold' => $own && is_string($this->low_balance_threshold) ? Money::round($this->low_balance_threshold, $decimals) : null,
            'balance' => $own && $this->balance !== null ? Money::round($this->balance, $decimals) : null,
            'opening_balance' => $this->when($this->resource->offsetExists('opening_balance'), fn () => $this->resource->getAttribute('opening_balance')),
            'opening_balance_date' => $this->when($this->resource->offsetExists('opening_balance_date'), fn () => $this->resource->getAttribute('opening_balance_date')),
            'active' => $this->active,
            'include_net_worth' => $this->include_net_worth,
            'order' => $this->order,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
