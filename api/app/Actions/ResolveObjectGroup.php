<?php

namespace App\Actions;

use App\Models\Ledger;

class ResolveObjectGroup
{
    /**
     * Turns the group title sent by a client into a group id, creating the group when it is new.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function handle(Ledger $ledger, array $attributes): array
    {
        if (! array_key_exists('object_group', $attributes)) {
            return $attributes;
        }

        $title = trim((string) $attributes['object_group']);
        unset($attributes['object_group']);

        $attributes['object_group_id'] = $title === ''
            ? null
            : $ledger->objectGroups()->firstOrCreate(['title' => $title])->id;

        return $attributes;
    }
}
