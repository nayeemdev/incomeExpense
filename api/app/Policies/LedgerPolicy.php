<?php

namespace App\Policies;

use App\Models\Ledger;
use App\Models\User;

class LedgerPolicy
{
    public function view(User $user, Ledger $ledger): bool
    {
        return $ledger->roleOf($user) !== null;
    }

    public function edit(User $user, Ledger $ledger): bool
    {
        return $ledger->roleOf($user)?->canEdit() ?? false;
    }

    public function manage(User $user, Ledger $ledger): bool
    {
        return $ledger->roleOf($user)?->canManage() ?? false;
    }
}
