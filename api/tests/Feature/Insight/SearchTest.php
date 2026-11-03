<?php

use App\Models\Account;
use App\Models\Ledger;
use App\Models\Transaction;
use App\Search\TransactionSearch;

function record(Ledger $ledger, Account $from, array $overrides): string
{
    return test()->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
        'type' => 'withdrawal', 'date' => '2026-03-10', 'description' => 'Thing', 'amount' => '10',
        'source_id' => $from->id, 'destination_name' => 'Shop', ...$overrides,
    ]]])->assertCreated()->json('data.transactions.0.id');
}

it('parses keys, quoted values, negation and free text', function () {
    $tokens = array_map(fn ($token) => $token->toArray(), (new TransactionSearch)->parse('coffee category:"Eating out" -tag:work url:x.com amount_more:5'));

    expect($tokens)->toBe([
        ['key' => 'description', 'value' => 'coffee', 'negated' => false],
        ['key' => 'category', 'value' => 'Eating out', 'negated' => false],
        ['key' => 'tag', 'value' => 'work', 'negated' => true],
        ['key' => 'description', 'value' => 'url:x.com', 'negated' => false],
        ['key' => 'amount_more', 'value' => '5', 'negated' => false],
    ]);
});

it('filters transactions with the query language', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();
    record($ledger, $checking, ['description' => 'Coffee', 'amount' => '4', 'category_name' => 'Eating out', 'tags' => ['work']]);
    record($ledger, $checking, ['description' => 'Coffee beans', 'amount' => '18', 'category_name' => 'Groceries']);
    record($ledger, $checking, ['description' => 'Dinner', 'amount' => '60', 'category_name' => 'Eating out', 'date' => '2026-04-02']);

    $search = fn (string $query) => $this->getJson("/api/v1/ledgers/{$ledger->id}/transactions?".http_build_query(['query' => $query]))->json('data.*.description');

    expect($search('coffee'))->toHaveCount(2)
        ->and($search('category:"eating out" -tag:work'))->toBe(['Dinner'])
        ->and($search('amount_more:10 date:2026-03'))->toBe(['Coffee beans'])
        ->and($search('has:tags'))->toBe(['Coffee'])
        ->and($search('-has:category'))->toBe([]);
});

it('saves searches per user', function () {
    $ledger = actingAsMember();

    $id = $this->postJson("/api/v1/ledgers/{$ledger->id}/saved-searches", ['name' => 'Big food', 'query' => 'category:food amount_more:50'])
        ->assertCreated()->json('data.id');
    $this->getJson("/api/v1/ledgers/{$ledger->id}/saved-searches")->assertJsonPath('data.0.name', 'Big food');
    $this->deleteJson("/api/v1/ledgers/{$ledger->id}/saved-searches/{$id}")->assertNoContent();
});

it('bulk edits and deletes transactions', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();
    $ids = [record($ledger, $checking, ['description' => 'A']), record($ledger, $checking, ['description' => 'B'])];

    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions/bulk", ['transaction_ids' => $ids, 'action' => 'set_category', 'value' => 'Misc'])
        ->assertJsonPath('data.updated', 2);
    expect(Transaction::query()->whereNull('category_id')->count())->toBe(0);

    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions/bulk", ['transaction_ids' => $ids, 'action' => 'notify'])
        ->assertJsonValidationErrors('action');
    $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions/bulk", ['transaction_ids' => $ids, 'action' => 'delete_transaction'])->assertOk();
    expect(Transaction::query()->count())->toBe(0);
});
