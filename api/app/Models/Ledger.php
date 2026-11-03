<?php

namespace App\Models;

use App\Enums\LedgerRole;
use Database\Factories\LedgerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'primary_currency_id', 'fiscal_year_start'])]
class Ledger extends Model
{
    /** @use HasFactory<LedgerFactory> */
    use HasFactory;

    /** @var array<int, LedgerRole|null> */
    private array $roleCache = [];

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function primaryCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'primary_currency_id');
    }

    /**
     * @return BelongsToMany<User, $this, LedgerMember>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(LedgerMember::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<LedgerInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(LedgerInvitation::class);
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<Tag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    /**
     * @return HasMany<TransactionGroup, $this>
     */
    public function transactionGroups(): HasMany
    {
        return $this->hasMany(TransactionGroup::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function roleOf(User $user): ?LedgerRole
    {
        if (! array_key_exists($user->id, $this->roleCache)) {
            $role = $this->members()->whereKey($user->id)->value('role');
            $this->roleCache[$user->id] = is_string($role) ? LedgerRole::from($role) : null;
        }

        return $this->roleCache[$user->id];
    }

    public function addMember(User $user, LedgerRole $role): void
    {
        $this->members()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);
        unset($this->roleCache[$user->id]);
    }
}
