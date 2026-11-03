<?php

namespace App\Models;

use App\Enums\LedgerRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property LedgerRole $role
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
