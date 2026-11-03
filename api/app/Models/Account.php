<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\AssetRole;
use App\Enums\InterestPeriod;
use App\Enums\LiabilityDirection;
use App\Enums\LiabilityType;
use App\Models\Concerns\BelongsToLedger;
use Carbon\CarbonInterface;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property AccountType $type
 * @property ?AssetRole $asset_role
 * @property ?LiabilityType $liability_type
 * @property ?LiabilityDirection $liability_direction
 * @property ?InterestPeriod $interest_period
 * @property string $virtual_balance
 * @property ?string $balance
 * @property-read ?Currency $currency
 */
#[Fillable([
    'type', 'name', 'currency_id', 'asset_role', 'liability_type', 'liability_direction',
    'interest', 'interest_period', 'credit_card_payment_day', 'iban', 'bic', 'account_number',
    'virtual_balance', 'active', 'include_net_worth', 'order', 'notes',
])]
class Account extends Model
{
    use BelongsToLedger;

    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'asset_role' => AssetRole::class,
            'liability_type' => LiabilityType::class,
            'liability_direction' => LiabilityDirection::class,
            'interest_period' => InterestPeriod::class,
            'active' => 'boolean',
            'include_net_worth' => 'boolean',
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
     * @return HasMany<Posting, $this>
     */
    public function postings(): HasMany
    {
        return $this->hasMany(Posting::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function withBalance(Builder $query, ?CarbonInterface $at = null): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('accounts.*');
        }

        $query->addSelect(['balance' => Posting::query()
            ->selectRaw('COALESCE(SUM(postings.amount), 0)')
            ->join('transactions', 'transactions.id', '=', 'postings.transaction_id')
            ->whereNull('transactions.deleted_at')
            ->whereColumn('postings.account_id', 'accounts.id')
            ->when($at, fn (Builder $q, CarbonInterface $at) => $q->where('transactions.date', '<=', $at->toDateString())),
        ]);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visible(Builder $query): void
    {
        $query->whereIn('type', AccountType::userManaged());
    }
}
