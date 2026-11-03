<?php

namespace App\RuleEngine;

use App\Enums\TransactionType;
use App\Models\Ledger;
use App\Models\Rule;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RuleEngine
{
    public const array TRANSACTION_RELATIONS = ['sourceAccount', 'destinationAccount', 'category', 'budget', 'bill', 'currency', 'foreignCurrency', 'tags', 'ledger', 'group'];

    /**
     * Runs the ledger's active rules over every split of a group that was just stored or updated.
     */
    public function applyToGroup(TransactionGroup $group, bool $created): void
    {
        $rules = $this->activeRules($group->ledger, $created ? 'store' : 'update');

        if ($rules->isEmpty()) {
            return;
        }

        $group->transactions()->with(self::TRANSACTION_RELATIONS)->get()->each(
            fn (Transaction $transaction) => $this->run($rules, $transaction),
        );
    }

    public function matches(Rule $rule, Transaction $transaction): bool
    {
        $results = $rule->triggers->map(fn ($trigger) => $trigger->type->matches($transaction, $trigger->value) !== $trigger->negated);

        if ($results->isEmpty()) {
            return false;
        }

        return $rule->strict ? ! $results->contains(false) : $results->contains(true);
    }

    /**
     * Applies one rule's actions, returning false when the transaction was deleted.
     */
    public function execute(Rule $rule, Transaction $transaction): bool
    {
        foreach ($rule->actions as $action) {
            if (! $action->type->apply($transaction, $action->value, $rule)) {
                return false;
            }

            $transaction->refresh()->load(self::TRANSACTION_RELATIONS);
        }

        return true;
    }

    /**
     * @return Builder<Transaction>
     */
    public function candidates(Ledger $ledger, ?string $start, ?string $end): Builder
    {
        return Transaction::query()
            ->whereBelongsTo($ledger)
            ->whereIn('type', TransactionType::userManaged())
            ->when($start, fn (Builder $query, string $date) => $query->where('date', '>=', $date))
            ->when($end, fn (Builder $query, string $date) => $query->where('date', '<=', $date))
            ->with(self::TRANSACTION_RELATIONS)
            ->orderByDesc('date')
            ->orderByDesc('id');
    }

    /**
     * @param  Collection<int, Rule>  $rules
     */
    private function run(Collection $rules, Transaction $transaction): void
    {
        $stoppedGroups = [];

        foreach ($rules as $rule) {
            if (in_array($rule->rule_group_id, $stoppedGroups, true) || ! $this->matches($rule, $transaction)) {
                continue;
            }

            if (! $this->execute($rule, $transaction)) {
                return;
            }

            if ($rule->stop_processing) {
                $stoppedGroups[] = $rule->rule_group_id;
            }

            if ($rule->group->stop_processing) {
                return;
            }
        }
    }

    /**
     * @return Collection<int, Rule>
     */
    private function activeRules(Ledger $ledger, string $moment): Collection
    {
        return Rule::query()
            ->join('rule_groups', 'rule_groups.id', '=', 'rules.rule_group_id')
            ->where('rules.ledger_id', $ledger->id)
            ->where('rules.active', true)
            ->where('rule_groups.active', true)
            ->whereIn('rules.trigger_on', [$moment, 'both'])
            ->orderBy('rule_groups.order')
            ->orderBy('rule_groups.id')
            ->orderBy('rules.order')
            ->orderBy('rules.id')
            ->select('rules.*')
            ->with('triggers', 'actions', 'group')
            ->get();
    }
}
