<?php

namespace App\Http\Resources;

use App\Models\LedgerInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LedgerInvitation */
class LedgerInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'ledger' => new LedgerResource($this->whenLoaded('ledger')),
            'invited_by' => $this->whenLoaded('inviter', fn () => $this->inviter->name),
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
        ];
    }
}
