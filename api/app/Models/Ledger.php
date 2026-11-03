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

    /** @var array<int, LedgerMember|null> */
    private array $membershipCache = [];

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
            ->withPivot('role', 'default_account_id')
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

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * @return HasMany<ObjectGroup, $this>
     */
    public function objectGroups(): HasMany
    {
        return $this->hasMany(ObjectGroup::class);
    }

    /**
     * @return HasMany<Budget, $this>
     */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    /**
     * @return HasMany<AvailableBudget, $this>
     */
    public function availableBudgets(): HasMany
    {
        return $this->hasMany(AvailableBudget::class);
    }

    /**
     * @return HasMany<Bill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /**
     * @return HasMany<PiggyBank, $this>
     */
    public function piggyBanks(): HasMany
    {
        return $this->hasMany(PiggyBank::class);
    }

    /**
     * @return HasMany<Recurrence, $this>
     */
    public function recurrences(): HasMany
    {
        return $this->hasMany(Recurrence::class);
    }

    /**
     * @return HasMany<TransactionLink, $this>
     */
    public function transactionLinks(): HasMany
    {
        return $this->hasMany(TransactionLink::class);
    }

    /**
     * @return HasMany<RuleGroup, $this>
     */
    public function ruleGroups(): HasMany
    {
        return $this->hasMany(RuleGroup::class);
    }

    /**
     * @return HasMany<Rule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(Rule::class);
    }

    /**
     * @return HasMany<Webhook, $this>
     */
    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    public function membershipOf(User $user): ?LedgerMember
    {
        if (! array_key_exists($user->id, $this->membershipCache)) {
            $pivot = $this->members()->whereKey($user->id)->first()?->getRelation('pivot');
            $this->membershipCache[$user->id] = $pivot instanceof LedgerMember ? $pivot : null;
        }

        return $this->membershipCache[$user->id];
    }

    public function roleOf(User $user): ?LedgerRole
    {
        return $this->membershipOf($user)?->role;
    }

    public function addMember(User $user, LedgerRole $role): void
    {
        $this->members()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);
        unset($this->membershipCache[$user->id]);
    }
}
