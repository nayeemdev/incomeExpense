<?php

namespace App\Http\Resources;

use App\Models\LedgerMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class LedgerMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var LedgerMember $membership */
        $membership = $this->resource->getRelation('pivot');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $membership->role,
            'joined_at' => $membership->created_at,
        ];
    }
}
