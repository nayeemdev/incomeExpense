<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'outward', 'inward'])]
final class LinkType extends Model
{
    /**
     * Built-in types (no ledger) plus the ledger's own.
     *
     * @return Builder<self>
     */
    public static function availableTo(Ledger $ledger): Builder
    {
        return self::query()->where(fn (Builder $query) => $query->whereNull('ledger_id')->orWhere('ledger_id', $ledger->id));
    }
}
