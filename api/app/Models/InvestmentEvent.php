<?php

namespace App\Models;

use App\Enums\InvestmentEventType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $investment_id
 * @property ?string $transaction_group_id
 * @property InvestmentEventType $type
 * @property CarbonImmutable $date
 * @property ?string $units
 * @property ?string $price
 * @property string $amount
 * @property ?string $cost
 * @property-read Investment $investment
 * @property-read ?TransactionGroup $transactionGroup
 */
#[Fillable(['transaction_group_id', 'type', 'date', 'units', 'price', 'amount', 'cost'])]
class InvestmentEvent extends Model
{
    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => InvestmentEventType::class,
            'date' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<Investment, $this>
     */
    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    /**
     * @return BelongsTo<TransactionGroup, $this>
     */
    public function transactionGroup(): BelongsTo
    {
        return $this->belongsTo(TransactionGroup::class);
    }
}
