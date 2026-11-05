<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLedger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * The share of the portfolio the ledger aims to keep in one kind of investment.
 *
 * @property string $kind
 * @property string $percent
 */
#[Fillable(['kind', 'percent'])]
class InvestmentTarget extends Model
{
    use BelongsToLedger, HasUlids;
}
