<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLedger;
use App\Models\Concerns\HasObjectGroup;
use Database\Factories\PiggyBankFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property ?string $target_amount
 * @property ?Carbon $start_date
 * @property ?Carbon $target_date
 * @property-read Currency $currency
 */
#[Fillable(['object_group_id', 'name', 'currency_id', 'target_amount', 'start_date', 'target_date', 'active', 'order', 'notes'])]
class PiggyBank extends Model
{
    use BelongsToLedger, HasUlids;

    /** @use HasFactory<PiggyBankFactory> */
    use HasFactory;

    use HasObjectGroup;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'target_date' => 'date',
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
     * @return BelongsToMany<Account, $this>
     */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class)->withPivot('current_amount');
    }

    /**
     * @return HasMany<PiggyBankEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(PiggyBankEvent::class);
    }
}
