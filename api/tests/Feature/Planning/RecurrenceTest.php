<?php

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\Date;

function recurrencePayload(Account $from, array $repetition, array $overrides = []): array
{
    return [
        'type' => 'withdrawal',
        'title' => 'Rent',
        'first_date' => '2026-01-01',
        'repetition' => $repetition,
        'transactions' => [['description' => 'Rent', 'amount' => '900', 'source_id' => $from->id, 'destination_name' => 'Landlord', 'category_name' => 'Housing']],
        ...$overrides,
    ];
}

it('creates the occurrences that are already due and lists upcoming ones', function () {
    Date::setTestNow('2026-03-15');
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/recurrences", recurrencePayload($checking, ['type' => 'monthly', 'moment' => '1']))
        ->assertCreated()
        ->assertJsonPath('data.occurrences_created', 3)
        ->assertJsonPath('data.upcoming.0', '2026-04-01');

    expect(Transaction::query()->pluck('date')->map->toDateString()->all())->toBe(['2026-01-01', '2026-02-01', '2026-03-01']);

    Date::setTestNow('2026-04-02');
    $this->artisan('recurring:create')->assertSuccessful();
    $this->artisan('recurring:create')->assertSuccessful();

    expect(Transaction::query()->count())->toBe(4);
});

it('respects the number of repetitions and weekend handling', function () {
    Date::setTestNow('2026-12-31');
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/recurrences", recurrencePayload(
        $checking,
        ['type' => 'monthly', 'moment' => '28', 'weekend' => 'before'],
        ['max_occurrences' => 3],
    ))->assertCreated();

    expect(Transaction::query()->orderBy('date')->pluck('date')->map->toDateString()->all())
        ->toBe(['2026-01-28', '2026-02-27', '2026-03-27']);
});

it('supports weekly and nth weekday schedules', function (array $repetition, array $expected) {
    Date::setTestNow('2026-02-01');
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/recurrences", recurrencePayload($checking, $repetition, ['first_date' => '2026-01-01']))
        ->assertCreated();

    expect(Transaction::query()->orderBy('date')->pluck('date')->map->toDateString()->all())->toBe($expected);
})->with([
    'every other Friday' => [['type' => 'weekly', 'moment' => '5', 'skip' => 1], ['2026-01-02', '2026-01-16', '2026-01-30']],
    'second Tuesday' => [['type' => 'ndom', 'moment' => '2,2'], ['2026-01-13']],
]);

it('validates the moment for the repetition type', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->create();

    $this->postJson("/api/v1/ledgers/{$ledger->id}/recurrences", recurrencePayload($checking, ['type' => 'weekly', 'moment' => '9']))
        ->assertJsonValidationErrors('repetition.moment');
});
