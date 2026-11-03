<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLedger;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'created_by'])]
class TransactionGroup extends Model
{
    use BelongsToLedger;
    use HasAttachments;
    use SoftDeletes;

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->orderBy('order');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deleteWithTransactions(): void
    {
        $this->transactions()->delete();
        $this->delete();
    }

    public function restoreWithTransactions(): void
    {
        $this->restore();
        $this->transactions()->onlyTrashed()->restore();
    }
}
