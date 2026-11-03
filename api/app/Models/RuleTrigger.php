<?php

namespace App\Models;

use App\RuleEngine\TriggerType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property TriggerType $type
 * @property ?string $value
 * @property bool $negated
 */
#[Fillable(['type', 'value', 'negated', 'order'])]
class RuleTrigger extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => TriggerType::class, 'negated' => 'boolean'];
    }
}
