<?php

namespace App\Models\Concerns;

use App\Models\Ledger;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $ledger_id
 * @property-read Ledger $ledger
 */
trait BelongsToLedger
{
    /**
     * @return BelongsTo<Ledger, $this>
     */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }
}
