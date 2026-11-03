<?php

use App\Models\Account;

it('exports transactions as CSV filtered by a search', function () {
    $ledger = actingAsMember();
    $checking = Account::factory()->for($ledger)->currency('USD')->create();

    foreach (['Coffee' => 'Eating out', 'Bus' => 'Transport'] as $description => $category) {
        $this->postJson("/api/v1/ledgers/{$ledger->id}/transactions", ['transactions' => [[
            'type' => 'withdrawal', 'date' => '2026-03-10', 'description' => $description, 'amount' => '3.5',
            'source_id' => $checking->id, 'destination_name' => 'Shop', 'category_name' => $category, 'tags' => ['a', 'b'],
        ]]]);
    }

    $csv = $this->get("/api/v1/ledgers/{$ledger->id}/export/transactions.csv?query=category:transport")->assertOk()->streamedContent();
    $lines = array_map('str_getcsv', array_filter(explode("\n", $csv)));

    expect($lines)->toHaveCount(2)
        ->and($lines[1][2])->toBe('Bus')
        ->and($lines[1][3])->toBe('3.50')
        ->and($lines[1][12])->toBe('a,b');
});

it('exports a full ledger backup', function () {
    $ledger = actingAsMember();
    Account::factory()->for($ledger)->create();

    $this->get("/api/v1/ledgers/{$ledger->id}/export/backup.json")
        ->assertOk()
        ->assertHeader('Content-Disposition')
        ->assertJsonPath('ledger.name', $ledger->name)
        ->assertJsonCount(1, 'accounts');
});
