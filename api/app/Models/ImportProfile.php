<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLedger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed> $config
 */
#[Fillable(['name', 'config'])]
class ImportProfile extends Model
{
    use BelongsToLedger;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['config' => 'array'];
    }
}
