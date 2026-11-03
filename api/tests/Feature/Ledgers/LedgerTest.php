<?php

use App\Enums\LedgerRole;
use App\Models\Currency;
use App\Models\Ledger;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('creates a ledger owned by the creator', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);
    $currency = Currency::findByCode('EUR');

    $this->postJson('/api/v1/ledgers', ['name' => 'Household', 'primary_currency_id' => $currency->id])
        ->assertCreated()
        ->assertJsonPath('data.role', 'owner')
        ->assertJsonPath('data.primary_currency.code', 'EUR');
});

it('starts a new ledger with styled default categories', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $id = $this->postJson('/api/v1/ledgers', ['name' => 'Household', 'primary_currency_id' => Currency::findByCode('EUR')->id])->json('data.id');
    $groceries = Ledger::query()->findOrFail($id)->categories()->where('name', 'Groceries')->firstOrFail();

    expect(Ledger::query()->findOrFail($id)->categories()->count())->toBe(count(config('ledgerfly.categories.defaults')))
        ->and($groceries->icon)->toBe('shopping-cart');
});

it('lists only the ledgers the user belongs to', function () {
    actingAsMember();
    Ledger::factory()->create();

    $this->getJson('/api/v1/ledgers')->assertJsonCount(1, 'data');
});

it('hides ledgers from non-members', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/v1/ledgers/'.Ledger::factory()->create()->id)->assertForbidden();
});

it('lets only owners update and delete a ledger', function (LedgerRole $role, int $status) {
    $ledger = actingAsMember($role);

    $this->patchJson("/api/v1/ledgers/{$ledger->id}", ['name' => 'Renamed'])->assertStatus($status);
})->with([
    'owner' => [LedgerRole::Owner, 200],
    'editor' => [LedgerRole::Editor, 403],
    'viewer' => [LedgerRole::Viewer, 403],
]);

it('lists the currencies', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson('/api/v1/currencies')
        ->assertOk()
        ->assertJsonFragment(['code' => 'JPY', 'decimal_places' => 0]);
});
