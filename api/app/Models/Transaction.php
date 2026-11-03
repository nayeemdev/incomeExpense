<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Models\Concerns\BelongsToLedger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property TransactionType $type
 * @property Carbon $date
 * @property string $amount
 * @property ?string $foreign_amount
 * @property ?string $running_balance
 * @property-read TransactionGroup $group
 * @property-read Account $sourceAccount
 * @property-read Account $destinationAccount
 * @property-read Currency $currency
 * @property-read ?Currency $foreignCurrency
 * @property-read ?Category $category
 * @property-read ?Budget $budget
 * @property-read ?Bill $bill
 */
#[Fillable([
    'ledger_id', 'type', 'date', 'description', 'source_account_id', 'destination_account_id',
    'currency_id', 'amount', 'foreign_currency_id', 'foreign_amount', 'category_id', 'budget_id', 'bill_id', 'import_job_id', 'external_id', 'notes', 'order',
])]
class Transaction extends Model
{
    use BelongsToLedger;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<TransactionGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(TransactionGroup::class, 'transaction_group_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'destination_account_id')->withTrashed();
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function foreignCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'foreign_currency_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return HasMany<Posting, $this>
     */
    public function postings(): HasMany
    {
        return $this->hasMany(Posting::class);
    }
}
