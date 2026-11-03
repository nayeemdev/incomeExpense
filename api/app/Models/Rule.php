<?php

namespace App\Models;

use App\Models\Concerns\BelongsToLedger;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read RuleGroup $group
 */
#[Fillable(['rule_group_id', 'title', 'description', 'trigger_on', 'strict', 'stop_processing', 'active', 'order'])]
class Rule extends Model
{
    use BelongsToLedger;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['strict' => 'boolean', 'stop_processing' => 'boolean', 'active' => 'boolean'];
    }

    /**
     * @return BelongsTo<RuleGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(RuleGroup::class, 'rule_group_id');
    }

    /**
     * @return HasMany<RuleTrigger, $this>
     */
    public function triggers(): HasMany
    {
        return $this->hasMany(RuleTrigger::class)->orderBy('order');
    }

    /**
     * @return HasMany<RuleAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(RuleAction::class)->orderBy('order');
    }
}
