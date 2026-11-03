<?php

namespace App\Events;

use App\Models\TransactionGroup;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionGroupSaved
{
    use Dispatchable, SerializesModels;

    public function __construct(public TransactionGroup $group, public bool $created) {}
}
