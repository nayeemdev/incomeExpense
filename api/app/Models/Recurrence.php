<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Models\Concerns\BelongsToLedger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property TransactionType $type
 * @property CarbonImmutable $first_date
 * @property ?CarbonImmutable $repeat_until
 * @property ?CarbonImmutable $latest_date
 * @property ?int $max_occurrences
 * @property int $occurrences_created
 * @property-read ?User $creator
 */
#[Fillable([
    'created_by', 'type', 'title', 'description', 'first_date', 'repeat_until', 'max_occurrences',
    'occurrences_created', 'latest_date', 'apply_rules', 'active',
])]
class Recurrence extends Model
{
    use BelongsToLedger;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'first_date' => 'immutable_date',
            'repeat_until' => 'immutable_date',
            'latest_date' => 'immutable_date',
            'apply_rules' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<RecurrenceRepetition, $this>
     */
    public function repetitions(): HasMany
    {
        return $this->hasMany(RecurrenceRepetition::class);
    }

    /**
     * @return HasMany<RecurrenceTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(RecurrenceTransaction::class)->orderBy('order');
    }

    /**
     * @return HasMany<TransactionGroup, $this>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(TransactionGroup::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
