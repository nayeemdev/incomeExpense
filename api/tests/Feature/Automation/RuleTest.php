<?php

use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;

function ruleGroup(Ledger $ledger): string
{
    return test()->postJson("/api/v1/ledgers/{$ledger->id}/rule-groups", ['title' => 'Default'])->assertCreated()->json('data.id');
}

function spendOn(Ledger $ledger, Account $from, string $description, string $amount = '10', string $date = '2026-03-01'): string
{
    return test()->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
        'type' => 'withdrawal', 'date' => $date, 'description' => $description, 'amount' => $amount,
        'source_id' => $from->id, 'destination_name' => $description,
    ]]])->assertCreated()->json('data.transactions.0.id');
}

it('applies matching rules when a transaction is stored', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();
    $group = ruleGroup($ledger);

    $this->postJson("/api/v1/ledgers/{$ledger->id}/rules", [
        'rule_group_id' => $group,
        'title' => 'Coffee shops',
        'triggers' => [['type' => 'description_contains', 'value' => 'coffee'], ['type' => 'amount_less', 'value' => '20']],
        'actions' => [['type' => 'set_category', 'value' => 'Eating out'], ['type' => 'add_tag', 'value' => 'caffeine'], ['type' => 'set_destination_account', 'value' => 'Cafe']],
    ])->assertCreated();

    $id = spendOn($ledger, $checking, 'Morning COFFEE', '4');
    $other = spendOn($ledger, $checking, 'Coffee machine', '300');

    $transaction = Transaction::with('category', 'tags', 'destinationAccount')->find($id);
    expect($transaction->category->name)->toBe('Eating out')
        ->and($transaction->tags->pluck('name')->all())->toBe(['caffeine'])
        ->and($transaction->destinationAccount->name)->toBe('Cafe')
        ->and($transaction->postings()->where('account_id', $transaction->destination_account_id)->exists())->toBeTrue()
        ->and(Transaction::find($other)->category_id)->toBeNull();
});

it('supports any-trigger rules, negation and stop processing', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();
    $group = ruleGroup($ledger);

    $this->postJson("/api/v1/ledgers/{$ledger->id}/rules", [
        'rule_group_id' => $group, 'title' => 'Streaming', 'strict' => false, 'stop_processing' => true,
        'triggers' => [['type' => 'description_is', 'value' => 'netflix'], ['type' => 'description_is', 'value' => 'spotify']],
        'actions' => [['type' => 'set_category', 'value' => 'Subscriptions']],
    ])->assertCreated();
    $this->postJson("/api/v1/ledgers/{$ledger->id}/rules", [
        'rule_group_id' => $group, 'title' => 'Everything else', 'order' => 1,
        'triggers' => [['type' => 'has_no_category', 'negated' => true]],
        'actions' => [['type' => 'append_description', 'value' => ' (checked)']],
    ])->assertCreated();

    $id = spendOn($ledger, $checking, 'Spotify');

    expect(Transaction::find($id)->description)->toBe('Spotify');
});

it('tests and applies a rule to existing transactions', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();
    spendOn($ledger, $checking, 'Uber ride', '12', '2026-02-01');
    spendOn($ledger, $checking, 'Uber Eats', '25', '2026-03-01');
    spendOn($ledger, $checking, 'Bus', '2', '2026-03-01');

    $rule = $this->postJson("/api/v1/ledgers/{$ledger->id}/rules", [
        'rule_group_id' => ruleGroup($ledger), 'title' => 'Uber', 'trigger_on' => 'update',
        'triggers' => [['type' => 'description_starts', 'value' => 'uber']],
        'actions' => [['type' => 'set_category', 'value' => 'Transport']],
    ])->json('data.id');

    $this->postJson("/api/v1/ledgers/{$ledger->id}/rules/{$rule}/test")->assertJsonCount(2, 'data');
    $this->postJson("/api/v1/ledgers/{$ledger->id}/rules/{$rule}/apply", ['start' => '2026-03-01'])->assertJsonPath('data.applied', 1);

    expect(Transaction::query()->whereNotNull('category_id')->count())->toBe(1);
});

it('requires values for triggers and actions that need them', function () {
    $ledger = actingAsMember();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/rules", [
        'rule_group_id' => ruleGroup($ledger), 'title' => 'Broken',
        'triggers' => [['type' => 'description_is']],
        'actions' => [['type' => 'clear_category']],
    ])->assertJsonValidationErrors('triggers.0.value');
});
