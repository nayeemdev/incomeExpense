<?php

namespace App\Http\Resources;

use App\Models\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ledger */
class LedgerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'primary_currency' => new CurrencyResource($this->whenLoaded('primaryCurrency')),
            'fiscal_year_start' => $this->fiscal_year_start,
            'role' => $user instanceof User ? $this->roleOf($user) : null,
            'created_at' => $this->created_at,
        ];
    }
}
