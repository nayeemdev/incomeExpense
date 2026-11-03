<?php

namespace App\Models\Concerns;

use App\Models\ObjectGroup;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property ?int $object_group_id
 * @property-read ?ObjectGroup $objectGroup
 */
trait HasObjectGroup
{
    /**
     * @return BelongsTo<ObjectGroup, $this>
     */
    public function objectGroup(): BelongsTo
    {
        return $this->belongsTo(ObjectGroup::class);
    }
}
