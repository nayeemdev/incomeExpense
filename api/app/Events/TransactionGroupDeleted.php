<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TransactionGroupDeleted
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(public int $ledgerId, public int $groupId, public array $snapshot) {}
}
