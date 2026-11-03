<?php

namespace App\Http\Resources;

use App\Models\BudgetLimit;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BudgetLimit */
class BudgetLimitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $decimals = $this->currency->decimal_places;
        $spent = $this->resource->getAttribute('spent');

        return [
            'id' => $this->id,
            'budget_id' => $this->budget_id,
            'currency' => new CurrencyResource($this->currency),
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'amount' => Money::round($this->amount, $decimals),
            'spent' => is_string($spent) ? Money::round($spent, $decimals) : null,
            'generated' => $this->generated,
        ];
    }
}
