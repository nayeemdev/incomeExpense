<?php

namespace App\Models;

use App\Enums\AutoBudgetType;
use App\Enums\RepeatFrequency;
use App\Models\Concerns\BelongsToLedger;
use Database\Factories\BudgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property ?AutoBudgetType $auto_budget_type
 * @property ?RepeatFrequency $auto_budget_period
 * @property ?string $auto_budget_amount
 * @property-read ?Currency $autoBudgetCurrency
 */
#[Fillable(['name', 'active', 'order', 'notes', 'auto_budget_type', 'auto_budget_currency_id', 'auto_budget_amount', 'auto_budget_period'])]
class Budget extends Model
{
    use BelongsToLedger, HasUlids;

    /** @use HasFactory<BudgetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'auto_budget_type' => AutoBudgetType::class,
            'auto_budget_period' => RepeatFrequency::class,
        ];
    }

    /**
     * @return HasMany<BudgetLimit, $this>
     */
    public function limits(): HasMany
    {
        return $this->hasMany(BudgetLimit::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function autoBudgetCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'auto_budget_currency_id');
    }
}
