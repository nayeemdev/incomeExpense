<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLedger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property 'csv'|'camt053' $format
 * @property 'uploaded'|'queued'|'running'|'completed'|'failed' $status
 * @property ?array<string, mixed> $config
 * @property ?array{created: int, duplicates: int, failed: int, failures: list<array{line: int, message: string}>} $stats
 * @property ?Carbon $completed_at
 * @property-read ?User $user
 */
#[Fillable(['user_id', 'format', 'filename', 'path', 'status', 'config', 'stats', 'error', 'completed_at'])]
#[Hidden(['path'])]
class ImportJob extends Model
{
    use BelongsToLedger, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'stats' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
