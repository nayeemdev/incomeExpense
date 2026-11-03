<?php

namespace App\Http\Resources;

use App\Models\Bill;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Bill */
class BillResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $decimals = $this->currency->decimal_places;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'object_group' => $this->objectGroup?->title,
            'currency' => new CurrencyResource($this->currency),
            'amount_min' => Money::round($this->amount_min, $decimals),
            'amount_max' => Money::round($this->amount_max, $decimals),
            'date' => $this->date->toDateString(),
            'repeat_freq' => $this->repeat_freq,
            'skip' => $this->skip,
            'end_date' => $this->end_date?->toDateString(),
            'extension_date' => $this->extension_date?->toDateString(),
            'active' => $this->active,
            'order' => $this->order,
            'notes' => $this->notes,
            'next_expected_date' => $this->when($this->resource->offsetExists('next_expected_date'), fn () => $this->resource->getAttribute('next_expected_date')),
            'expected_dates' => $this->when($this->resource->offsetExists('expected_dates'), fn () => $this->resource->getAttribute('expected_dates')),
            'payments' => $this->when($this->resource->offsetExists('payments'), fn () => $this->resource->getAttribute('payments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
