<?php

namespace App\Http\Resources;

use App\Models\Rule;
use App\Models\RuleAction;
use App\Models\RuleTrigger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Rule */
class RuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rule_group_id' => $this->rule_group_id,
            'title' => $this->title,
            'description' => $this->description,
            'trigger_on' => $this->trigger_on,
            'strict' => $this->strict,
            'stop_processing' => $this->stop_processing,
            'active' => $this->active,
            'order' => $this->order,
            'triggers' => $this->triggers->map(fn (RuleTrigger $trigger) => [
                'type' => $trigger->type,
                'value' => $trigger->value,
                'negated' => $trigger->negated,
            ]),
            'actions' => $this->actions->map(fn (RuleAction $action) => [
                'type' => $action->type,
                'value' => $action->value,
            ]),
        ];
    }
}
