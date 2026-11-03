<?php

namespace App\Models;

use App\Enums\RepeatFrequency;
use App\Models\Concerns\BelongsToLedger;
use App\Models\Concerns\HasObjectGroup;
use Carbon\CarbonImmutable;
use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable $date
 * @property ?CarbonImmutable $end_date
 * @property ?CarbonImmutable $extension_date
 * @property RepeatFrequency $repeat_freq
 * @property string $amount_min
 * @property string $amount_max
 * @property int $skip
 * @property-read Currency $currency
 */
#[Fillable([
    'object_group_id', 'name', 'currency_id', 'amount_min', 'amount_max', 'date', 'repeat_freq',
    'skip', 'end_date', 'extension_date', 'active', 'order', 'notes',
])]
class Bill extends Model
{
    use BelongsToLedger;

    /** @use HasFactory<BillFactory> */
    use HasFactory;

    use HasObjectGroup;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'extension_date' => 'immutable_date',
            'repeat_freq' => RepeatFrequency::class,
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Dates the bill is expected to be paid between $start and $end (inclusive).
     *
     * @return list<CarbonImmutable>
     */
    public function expectedDates(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $dates = [];
        $step = $this->skip + 1;
        $date = $this->date;
        $last = $this->end_date !== null && $this->end_date->lessThan($end) ? $this->end_date : $end;

        for ($index = 1; $date->lessThanOrEqualTo($last); $index++) {
            if ($date->greaterThanOrEqualTo($start)) {
                $dates[] = $date;
            }

            $date = $this->repeat_freq->add($this->date, $step * $index);
        }

        return $dates;
    }

    public function nextExpectedDate(CarbonImmutable $from): ?CarbonImmutable
    {
        $horizon = $this->repeat_freq->add($from, ($this->skip + 1) * 2);

        return $this->expectedDates($from, $horizon)[0] ?? null;
    }
}
