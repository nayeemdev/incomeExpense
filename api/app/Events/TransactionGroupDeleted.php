<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TransactionGroupDeleted
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(public string $ledgerId, public string $groupId, public array $snapshot) {}
}
