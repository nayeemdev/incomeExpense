<?php

use App\Models\Category;
use App\Models\Tag;

it('manages categories', function () {
    $ledger = actingAsMember();

    $id = $this->postJson("/api/v1/ledgers/{$ledger->id}/categories", ['name' => 'Food'])->assertCreated()->json('data.id');
    $this->postJson("/api/v1/ledgers/{$ledger->id}/categories", ['name' => 'Food'])->assertJsonValidationErrors('name');
    $this->patchJson("/api/v1/ledgers/{$ledger->id}/categories/{$id}", ['name' => 'Groceries'])->assertJsonPath('data.name', 'Groceries');
    $this->getJson("/api/v1/ledgers/{$ledger->id}/categories?search=groc")->assertJsonCount(1, 'data');
    $this->deleteJson("/api/v1/ledgers/{$ledger->id}/categories/{$id}")->assertNoContent();

    expect(Category::query()->count())->toBe(0);
});

it('manages tags', function () {
    $ledger = actingAsMember();

    $id = $this->postJson("/api/v1/ledgers/{$ledger->id}/tags", ['name' => 'holiday', 'date' => '2026-07-01'])
        ->assertCreated()
        ->assertJsonPath('data.date', '2026-07-01')
        ->json('data.id');
    $this->deleteJson("/api/v1/ledgers/{$ledger->id}/tags/{$id}")->assertNoContent();

    expect(Tag::query()->count())->toBe(0);
});

it('allows the same category name in different ledgers', function () {
    $ledger = actingAsMember();
    Category::factory()->create(['name' => 'Food']);

    $this->postJson("/api/v1/ledgers/{$ledger->id}/categories", ['name' => 'Food'])->assertCreated();
});
