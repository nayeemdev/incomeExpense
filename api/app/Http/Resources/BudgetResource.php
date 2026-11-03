<?php

namespace App\Http\Resources;

use App\Models\Budget;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Budget */
class BudgetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'active' => $this->active,
            'order' => $this->order,
            'notes' => $this->notes,
            'auto_budget_type' => $this->auto_budget_type,
            'auto_budget_period' => $this->auto_budget_period,
            'auto_budget_currency' => $this->autoBudgetCurrency ? new CurrencyResource($this->autoBudgetCurrency) : null,
            'auto_budget_amount' => $this->autoBudgetCurrency && $this->auto_budget_amount !== null
                ? Money::round($this->auto_budget_amount, $this->autoBudgetCurrency->decimal_places)
                : null,
            'limits' => BudgetLimitResource::collection($this->whenLoaded('limits')),
            'spent' => $this->when($this->resource->offsetExists('spent'), fn () => $this->resource->getAttribute('spent')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
