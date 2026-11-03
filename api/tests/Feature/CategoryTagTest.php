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

it('gives new categories an icon and color from their name', function () {
    $ledger = actingAsMember();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/categories", ['name' => 'Weekly Groceries'])
        ->assertCreated()
        ->assertJsonPath('data.icon', 'shopping-cart')
        ->assertJsonPath('data.color', 'green');

    $this->postJson("/api/v1/ledgers/{$ledger->id}/categories", ['name' => 'Odds and ends'])
        ->assertJsonPath('data.icon', 'tag')
        ->assertJsonPath('data.color', 'slate');
});

it('lets the icon and color be chosen from the predefined set', function () {
    $ledger = actingAsMember();

    $id = $this->postJson("/api/v1/ledgers/{$ledger->id}/categories", ['name' => 'Coffee', 'icon' => 'gift', 'color' => 'violet'])
        ->assertJsonPath('data.icon', 'gift')
        ->json('data.id');

    $this->patchJson("/api/v1/ledgers/{$ledger->id}/categories/{$id}", ['icon' => 'not-an-icon', 'color' => 'neon'])
        ->assertJsonValidationErrors(['icon', 'color']);
});

it('lists the icons and colors a category can use', function () {
    actingAsMember();

    $this->getJson('/api/v1/category-styles')
        ->assertOk()
        ->assertJsonPath('data.icons', config('ledgerfly.categories.icons'))
        ->assertJsonPath('data.colors', config('ledgerfly.categories.colors'));
});
