<?php

namespace App\Models;

use App\Enums\LedgerRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property LedgerRole $role
 * @property ?int $default_account_id
 * @property ?int $default_income_account_id
 */
class LedgerMember extends Pivot
{
    public $incrementing = true;

    protected $table = 'ledger_user';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['role' => LedgerRole::class];
    }
}
