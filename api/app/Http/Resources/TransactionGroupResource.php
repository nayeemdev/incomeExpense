<?php

namespace App\Http\Resources;

use App\Models\TransactionGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TransactionGroup */
class TransactionGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'transactions' => TransactionResource::collection($this->whenLoaded('transactions')),
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator?->only('id', 'name')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
