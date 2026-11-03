<?php

namespace App\Models;

use App\RuleEngine\ActionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property ActionType $type
 * @property ?string $value
 */
#[Fillable(['type', 'value', 'order'])]
class RuleAction extends Model
{
    use HasUlids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => ActionType::class];
    }
}
