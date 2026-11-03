<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'decimal_places'])]
class Currency extends Model
{
    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', strtoupper($code))->first();
    }
}
