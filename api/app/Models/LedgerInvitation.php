<?php

namespace App\Models;

use App\Enums\LedgerRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property LedgerRole $role
 * @property Carbon $expires_at
 * @property-read Ledger $ledger
 * @property-read User $inviter
 */
#[Fillable(['email', 'role', 'token', 'expires_at', 'invited_by'])]
#[Hidden(['token'])]
class LedgerInvitation extends Model
{
    use HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => LedgerRole::class,
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ledger, $this>
     */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public static function findByPlainToken(string $token): ?self
    {
        return static::query()
            ->where('token', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();
    }
}
