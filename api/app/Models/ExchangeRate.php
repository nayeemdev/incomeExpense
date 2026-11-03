<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Rates without a ledger are fetched for everyone; a ledger's own rates take precedence.
 *
 * @property ?int $ledger_id
 * @property Carbon $date
 * @property string $rate
 * @property-read Currency $from
 * @property-read Currency $to
 */
#[Fillable(['ledger_id', 'from_currency_id', 'to_currency_id', 'date', 'rate'])]
class ExchangeRate extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function from(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'from_currency_id');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function to(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'to_currency_id');
    }
}
