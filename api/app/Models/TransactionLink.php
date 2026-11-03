<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLedger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read LinkType $linkType
 * @property-read TransactionGroup $outward
 * @property-read TransactionGroup $inward
 */
#[Fillable(['link_type_id', 'outward_group_id', 'inward_group_id', 'notes'])]
class TransactionLink extends Model
{
    use BelongsToLedger, HasUlids;

    /**
     * @return BelongsTo<LinkType, $this>
     */
    public function linkType(): BelongsTo
    {
        return $this->belongsTo(LinkType::class);
    }

    /**
     * @return BelongsTo<TransactionGroup, $this>
     */
    public function outward(): BelongsTo
    {
        return $this->belongsTo(TransactionGroup::class, 'outward_group_id');
    }

    /**
     * @return BelongsTo<TransactionGroup, $this>
     */
    public function inward(): BelongsTo
    {
        return $this->belongsTo(TransactionGroup::class, 'inward_group_id');
    }
}
