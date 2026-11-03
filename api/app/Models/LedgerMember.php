<?php

namespace App\Models;

use App\Enums\LedgerRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property LedgerRole $role
 * @property ?string $default_account_id
 * @property ?string $default_income_account_id
 */
class LedgerMember extends Pivot
{
    use HasUlids;

    protected $table = 'ledger_user';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['role' => LedgerRole::class];
    }
}
